<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskRepository extends BaseRepository implements TaskRepositoryInterface
{
    public function __construct(Task $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['project', 'column', 'assignees']);

        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['project_column_id'])) {
            $query->where('project_column_id', $filters['project_column_id']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        if (isset($filters['assignee_id'])) {
            $query->whereHas('assignees', fn ($q) => $q->where('users.id', $filters['assignee_id']));
        }

        if (isset($filters['deadline'])) {
            match ($filters['deadline']) {
                'overdue' => $query->where('deadline', '<', now())->whereNotNull('deadline'),
                'today' => $query->whereDate('deadline', today()),
                'upcoming' => $query->whereBetween('deadline', [now(), now()->addDays(7)]),
                default => null,
            };
        }

        if (isset($filters['deadline_from'])) {
            $query->where('deadline', '>=', $filters['deadline_from']);
        }

        if (isset($filters['deadline_to'])) {
            $query->where('deadline', '<=', $filters['deadline_to']);
        }

        $orderBy = $filters['order_by'] ?? 'order';
        $orderDir = $filters['order_dir'] ?? 'asc';

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findByProject(int $projectId): LengthAwarePaginator
    {
        return $this->model->where('project_id', $projectId)->orderBy('order')->paginate(15);
    }
}
