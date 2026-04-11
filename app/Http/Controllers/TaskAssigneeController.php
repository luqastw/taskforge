<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MemberResource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskAssigneeController extends Controller
{
    /**
     * List assignees of a task.
     */
    public function index(Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $assignees = $task->assignees()
            ->with('roles')
            ->paginate(request('per_page', 15));

        return MemberResource::collection($assignees);
    }

    /**
     * Assign a user to a task.
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('assign', $task);

        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::where('tenant_id', $request->user()->tenant_id)
            ->find($request->user_id);

        if (! $user) {
            return $this->errorResponse('User not found in your tenant', 404);
        }

        // Validate user is a member of the project
        if (! $task->project->members()->where('user_id', $user->id)->exists()) {
            return $this->errorResponse('User must be a member of the project to be assigned', 422);
        }

        if ($task->assignees()->where('user_id', $user->id)->exists()) {
            return $this->errorResponse('User is already assigned to this task', 422);
        }

        $task->assignees()->attach($user->id, [
            'assigned_at' => now(),
        ]);

        activity('task_assignee_added')
            ->performedOn($task)
            ->causedBy($request->user())
            ->withProperties([
                'user_id' => $user->id,
                'user_name' => $user->name,
            ])
            ->log("User {$user->name} was assigned to task {$task->title}");

        return $this->successResponse(
            new MemberResource($user->load('roles')),
            'User assigned to task successfully',
            201
        );
    }

    /**
     * Assign multiple users to a task.
     */
    public function storeBulk(Request $request, Task $task): JsonResponse
    {
        $this->authorize('assign', $task);

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

        $projectMemberIds = $task->project->members()->pluck('users.id')->toArray();

        $assignedCount = 0;
        $alreadyAssignedCount = 0;
        $notProjectMemberCount = 0;

        foreach ($users as $user) {
            if (! in_array($user->id, $projectMemberIds)) {
                $notProjectMemberCount++;

                continue;
            }

            if ($task->assignees()->where('user_id', $user->id)->exists()) {
                $alreadyAssignedCount++;

                continue;
            }

            $task->assignees()->attach($user->id, [
                'assigned_at' => now(),
            ]);
            $assignedCount++;
        }

        if ($assignedCount > 0) {
            activity('task_assignees_added')
                ->performedOn($task)
                ->causedBy($request->user())
                ->withProperties([
                    'assigned_count' => $assignedCount,
                    'user_ids' => $users->pluck('id')->toArray(),
                ])
                ->log("{$assignedCount} users were assigned to task {$task->title}");
        }

        return $this->successResponse([
            'assigned' => $assignedCount,
            'already_assigned' => $alreadyAssignedCount,
            'not_project_members' => $notProjectMemberCount,
        ], "{$assignedCount} users assigned to task");
    }

    /**
     * Remove a user from a task.
     */
    public function destroy(Request $request, Task $task, int $userId): JsonResponse
    {
        $this->authorize('assign', $task);

        $user = User::where('tenant_id', $request->user()->tenant_id)
            ->find($userId);

        if (! $user) {
            return $this->errorResponse('User not found', 404);
        }

        if (! $task->assignees()->where('user_id', $user->id)->exists()) {
            return $this->errorResponse('User is not assigned to this task', 422);
        }

        $task->assignees()->detach($user->id);

        activity('task_assignee_removed')
            ->performedOn($task)
            ->causedBy($request->user())
            ->withProperties([
                'user_id' => $user->id,
                'user_name' => $user->name,
            ])
            ->log("User {$user->name} was unassigned from task {$task->title}");

        return $this->successResponse(null, 'User removed from task successfully');
    }
}
