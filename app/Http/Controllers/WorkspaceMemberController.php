<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceMemberController extends Controller
{
    /**
     * List all members of a workspace.
     */
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('view', $workspace);

        $members = $workspace->members()
            ->with('roles')
            ->paginate(request('per_page', 15));

        return MemberResource::collection($members);
    }

    /**
     * Add a member to a workspace.
     */
    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('update', $workspace);

        $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ], [
            'user_id.required' => 'User ID is required',
            'user_id.exists' => 'User not found',
        ]);

        $user = User::where('tenant_id', $request->user()->tenant_id)
            ->find($request->user_id);

        if (! $user) {
            return $this->errorResponse('User not found in your tenant', 404);
        }

        if ($workspace->members()->where('user_id', $user->id)->exists()) {
            return $this->errorResponse('User is already a member of this workspace', 422);
        }

        $workspace->members()->attach($user->id, [
            'joined_at' => now(),
        ]);

        activity('workspace_member_added')
            ->performedOn($workspace)
            ->causedBy($request->user())
            ->withProperties([
                'user_id' => $user->id,
                'user_name' => $user->name,
            ])
            ->log("Member {$user->name} was added to workspace {$workspace->name}");

        return $this->successResponse(
            new MemberResource($user->load('roles')),
            'Member added to workspace successfully',
            201
        );
    }

    /**
     * Remove a member from a workspace.
     */
    public function destroy(Request $request, Workspace $workspace, int $userId): JsonResponse
    {
        $this->authorize('update', $workspace);

        $user = User::where('tenant_id', $request->user()->tenant_id)
            ->find($userId);

        if (! $user) {
            return $this->errorResponse('User not found', 404);
        }

        if (! $workspace->members()->where('user_id', $user->id)->exists()) {
            return $this->errorResponse('User is not a member of this workspace', 422);
        }

        $workspace->members()->detach($user->id);

        activity('workspace_member_removed')
            ->performedOn($workspace)
            ->causedBy($request->user())
            ->withProperties([
                'user_id' => $user->id,
                'user_name' => $user->name,
            ])
            ->log("Member {$user->name} was removed from workspace {$workspace->name}");

        return $this->successResponse(null, 'Member removed from workspace successfully');
    }

    /**
     * Add multiple members to a workspace.
     */
    public function addMultiple(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('update', $workspace);

        $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $users = User::where('tenant_id', $request->user()->tenant_id)
            ->whereIn('id', $request->user_ids)
            ->get();

        if ($users->isEmpty()) {
            return $this->errorResponse('No valid users found in your tenant', 404);
        }

        $addedCount = 0;
        $alreadyMemberCount = 0;

        foreach ($users as $user) {
            if ($workspace->members()->where('user_id', $user->id)->exists()) {
                $alreadyMemberCount++;

                continue;
            }

            $workspace->members()->attach($user->id, [
                'joined_at' => now(),
            ]);
            $addedCount++;
        }

        if ($addedCount > 0) {
            activity('workspace_members_added')
                ->performedOn($workspace)
                ->causedBy($request->user())
                ->withProperties([
                    'added_count' => $addedCount,
                    'user_ids' => $users->pluck('id')->toArray(),
                ])
                ->log("{$addedCount} members were added to workspace {$workspace->name}");
        }

        return $this->successResponse([
            'added' => $addedCount,
            'already_members' => $alreadyMemberCount,
        ], "{$addedCount} members added to workspace");
    }
}
