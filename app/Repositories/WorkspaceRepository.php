<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class WorkspaceRepository extends BaseRepository implements WorkspaceRepositoryInterface
{
    public function __construct(Workspace $model)
    {
        parent::__construct($model);
    }

    /**
     * Get paginated workspaces with filters.
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Search workspaces by name.
     */
    public function search(string $query): LengthAwarePaginator
    {
        return $this->model
            ->where('name', 'like', '%'.$query.'%')
            ->orWhere('description', 'like', '%'.$query.'%')
            ->latest()
            ->paginate(15);
    }

    /**
     * Get workspace with projects count.
     */
    public function findWithProjectsCount(int $id): ?Workspace
    {
        return $this->model
            ->withCount('projects')
            ->find($id);
    }
}
