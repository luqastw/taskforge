<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskService
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    public function getAllTasks(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->taskRepository->paginate($perPage, $filters);
    }

    public function createTask(array $data): Task
    {
        return $this->taskRepository->create($data);
    }

    public function getTaskById(int $id): ?Task
    {
        return $this->taskRepository->find($id);
    }

    public function updateTask(Task $task, array $data): Task
    {
        $this->taskRepository->update($task->id, $data);

        return $task->fresh();
    }

    public function deleteTask(Task $task): bool
    {
        return $this->taskRepository->delete($task->id);
    }
}
