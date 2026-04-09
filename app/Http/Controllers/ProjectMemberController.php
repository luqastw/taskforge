<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MemberResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectMemberController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $members = $project->members()
            ->with('roles')
            ->paginate(request('per_page', 15));

        return MemberResource::collection($members);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

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

        if (! $project->workspace->members()->where('user_id', $user->id)->exists()) {
            return $this->errorResponse('User must be a member of the workspace first', 422);
        }

        if ($project->members()->where('user_id', $user->id)->exists()) {
            return $this->errorResponse('User is already a member of this project', 422);
        }

        $project->members()->attach($user->id, [
            'joined_at' => now(),
        ]);

        activity('project_member_added')
            ->performedOn($project)
            ->causedBy($request->user())
            ->withProperties([
                'user_id' => $user->id,
                'user_name' => $user->name,
            ])
            ->log("Member {$user->name} was added to project {$project->name}");

        return $this->successResponse(
            new MemberResource($user->load('roles')),
            'Member added to project successfully',
            201
        );
    }

    public function destroy(Request $request, Project $project, int $userId): JsonResponse
    {
        $this->authorize('manageMembers', $project);

        $user = User::where('tenant_id', $request->user()->tenant_id)
            ->find($userId);

        if (! $user) {
            return $this->errorResponse('User not found', 404);
        }

        if (! $project->members()->where('user_id', $user->id)->exists()) {
            return $this->errorResponse('User is not a member of this project', 422);
        }

        $project->members()->detach($user->id);

        activity('project_member_removed')
            ->performedOn($project)
            ->causedBy($request->user())
            ->withProperties([
                'user_id' => $user->id,
                'user_name' => $user->name,
            ])
            ->log("Member {$user->name} was removed from project {$project->name}");

        return $this->successResponse(null, 'Member removed from project successfully');
    }

    public function addMultiple(Request $request, Project $project): JsonResponse
    {
        $this->authorize('manageMembers', $project);

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

        $workspaceMemberIds = $project->workspace->members()->pluck('users.id')->toArray();

        $addedCount = 0;
        $alreadyMemberCount = 0;
        $notInWorkspaceCount = 0;

        foreach ($users as $user) {
            if (! in_array($user->id, $workspaceMemberIds)) {
                $notInWorkspaceCount++;

                continue;
            }

            if ($project->members()->where('user_id', $user->id)->exists()) {
                $alreadyMemberCount++;

                continue;
            }

            $project->members()->attach($user->id, [
                'joined_at' => now(),
            ]);
            $addedCount++;
        }

        if ($addedCount > 0) {
            activity('project_members_added')
                ->performedOn($project)
                ->causedBy($request->user())
                ->withProperties([
                    'added_count' => $addedCount,
                    'user_ids' => $users->pluck('id')->toArray(),
                ])
                ->log("{$addedCount} members were added to project {$project->name}");
        }

        return $this->successResponse([
            'added' => $addedCount,
            'already_members' => $alreadyMemberCount,
            'not_in_workspace' => $notInWorkspaceCount,
        ], "{$addedCount} members added to project");
    }
}
