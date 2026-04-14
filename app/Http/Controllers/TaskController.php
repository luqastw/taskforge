<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\ProjectColumn;
use App\Models\Task;
use App\Notifications\TaskStatusChangedNotification;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Task::class);

        $perPage = (int) $request->get('per_page', 15);
        $filters = $request->only([
            'project_id', 'project_column_id', 'priority', 'parent_id',
            'assignee_id', 'tag_id', 'deadline', 'deadline_from', 'deadline_to',
            'order_by', 'order_dir',
        ]);

        $tasks = $this->taskService->getAllTasks($perPage, $filters);

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): TaskResource
    {
        $task = $this->taskService->createTask($request->validated());

        return new TaskResource($task);
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        $task = $this->taskService->getTaskById($task->id);

        return new TaskResource($task);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $oldColumnId = $task->project_column_id;

        $task = $this->taskService->updateTask($task, $request->validated());

        if ($request->has('project_column_id') && $oldColumnId !== $task->project_column_id) {
            $this->notifyStatusChange($task, $oldColumnId, $request->user());
        }

        return new TaskResource($task);
    }

    public function destroy(Task $task): Response
    {
        $this->authorize('delete', $task);

        $this->taskService->deleteTask($task);

        return response()->noContent();
    }

    /**
     * List subtasks of a task.
     */
    public function subtasks(Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $subtasks = $task->subtasks()
            ->with(['column', 'assignees'])
            ->orderBy('order')
            ->paginate(request('per_page', 15));

        return TaskResource::collection($subtasks);
    }

    protected function notifyStatusChange(Task $task, int $oldColumnId, $changedBy): void
    {
        $oldColumn = ProjectColumn::find($oldColumnId);
        $newColumn = ProjectColumn::find($task->project_column_id);

        if (! $oldColumn || ! $newColumn) {
            return;
        }

        $assignees = $task->assignees()->where('users.id', '!=', $changedBy->id)->get();

        foreach ($assignees as $assignee) {
            $assignee->notify(new TaskStatusChangedNotification(
                $task,
                $changedBy,
                $oldColumn->name,
                $newColumn->name,
            ));
        }
    }
}
