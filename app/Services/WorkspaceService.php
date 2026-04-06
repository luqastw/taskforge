<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class WorkspaceService
{
    public function __construct(
        private readonly WorkspaceRepositoryInterface $workspaceRepository
    ) {}

    /**
     * Get all workspaces paginated.
     */
    public function getAllWorkspaces(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->workspaceRepository->paginate($perPage, $filters);
    }

    /**
     * Create a new workspace.
     */
    public function createWorkspace(array $data): Workspace
    {
        return $this->workspaceRepository->create($data);
    }

    /**
     * Get a workspace by ID.
     */
    public function getWorkspaceById(int $id): ?Workspace
    {
        return $this->workspaceRepository->findWithProjectsCount($id);
    }

    /**
     * Update a workspace.
     */
    public function updateWorkspace(Workspace $workspace, array $data): Workspace
    {
        $this->workspaceRepository->update($workspace->id, $data);

        return $workspace->fresh();
    }

    /**
     * Delete a workspace (soft delete).
     */
    public function deleteWorkspace(Workspace $workspace): bool
    {
        return $this->workspaceRepository->delete($workspace->id);
    }

    /**
     * Search workspaces by name.
     */
    public function searchWorkspaces(string $query): LengthAwarePaginator
    {
        return $this->workspaceRepository->search($query);
    }
}
