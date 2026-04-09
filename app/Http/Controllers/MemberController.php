<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    /**
     * List all members of the current tenant.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('roles');

        // Filter by role
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->input('role'));
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $members = $query->paginate($request->input('per_page', 15));

        return MemberResource::collection($members);
    }

    /**
     * Get a specific member.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $member = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('roles')
            ->find($id);

        if (! $member) {
            return $this->errorResponse('Member not found', 404);
        }

        return $this->successResponse(new MemberResource($member));
    }

    /**
     * Update a member's role or status.
     */
    public function update(UpdateMemberRequest $request, int $id): JsonResponse
    {
        $currentUser = $request->user();

        $member = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $currentUser->tenant_id)
            ->find($id);

        if (! $member) {
            return $this->errorResponse('Member not found', 404);
        }

        // Cannot update yourself through this endpoint
        if ($member->id === $currentUser->id) {
            return $this->errorResponse('Cannot update your own role through this endpoint', 422);
        }

        // Cannot update the owner
        if ($member->hasRole('owner')) {
            return $this->errorResponse('Cannot update the owner', 422);
        }

        // Only owner can promote to admin
        $newRole = $request->input('role');
        if ($newRole === 'admin' && ! $currentUser->hasRole('owner')) {
            return $this->errorResponse('Only owner can promote members to admin', 403);
        }

        // Cannot assign owner role through this endpoint
        if ($newRole === 'owner') {
            return $this->errorResponse('Use transfer-ownership endpoint to assign owner role', 422);
        }

        DB::transaction(function () use ($member, $request, $newRole) {
            if ($newRole) {
                $member->syncRoles([$newRole]);
            }

            if ($request->has('is_active')) {
                $member->is_active = $request->boolean('is_active');
                $member->save();
            }

            // Log the activity
            activity('member_updated')
                ->performedOn($member)
                ->causedBy(request()->user())
                ->withProperties([
                    'role' => $newRole,
                    'is_active' => $member->is_active,
                ])
                ->log("Member {$member->name} was updated");
        });

        return $this->successResponse(
            new MemberResource($member->fresh()->load('roles')),
            'Member updated successfully'
        );
    }

    /**
     * Remove a member from the tenant.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();

        $member = User::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $currentUser->tenant_id)
            ->find($id);

        if (! $member) {
            return $this->errorResponse('Member not found', 404);
        }

        // Cannot remove yourself
        if ($member->id === $currentUser->id) {
            return $this->errorResponse('Cannot remove yourself', 422);
        }

        // Cannot remove the owner
        if ($member->hasRole('owner')) {
            return $this->errorResponse('Cannot remove the owner. Transfer ownership first.', 422);
        }

        // Only owner can remove admins
        if ($member->hasRole('admin') && ! $currentUser->hasRole('owner')) {
            return $this->errorResponse('Only owner can remove admins', 403);
        }

        DB::transaction(function () use ($member, $currentUser) {
            // Log the removal before deleting
            activity('member_removed')
                ->performedOn($member)
                ->causedBy($currentUser)
                ->withProperties([
                    'member_id' => $member->id,
                    'member_name' => $member->name,
                    'member_email' => $member->email,
                ])
                ->log("Member {$member->name} was removed from the tenant");

            $member->tasks()->detach();
            $member->projects()->detach();
            $member->workspaces()->detach();

            // Remove member's roles
            $member->syncRoles([]);

            // Soft delete or deactivate the member
            $member->is_active = false;
            $member->save();

            // Optionally hard delete if you prefer
            // $member->delete();
        });

        return $this->successResponse(null, 'Member removed successfully');
    }
}
