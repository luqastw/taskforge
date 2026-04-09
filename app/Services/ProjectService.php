<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ProjectStatusChanged;
use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectColumnService $columnService
    ) {}

    public function getAllProjects(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->projectRepository->paginate($perPage, $filters);
    }

    public function createProject(array $data): Project
    {
        $project = $this->projectRepository->create($data);

        // Create default columns
        $this->columnService->createDefaultColumns($project);

        return $project->fresh('columns');
    }

    public function getProjectById(int $id): ?Project
    {
        return $this->projectRepository->find($id);
    }

    public function updateProject(Project $project, array $data): Project
    {
        $previousStatus = $project->status;

        $this->projectRepository->update($project->id, $data);

        $project = $project->fresh();

        if (isset($data['status']) && $previousStatus !== $data['status']) {
            ProjectStatusChanged::dispatch(
                $project,
                $previousStatus,
                $data['status'],
                auth()->user()
            );
        }

        return $project;
    }

    public function deleteProject(Project $project): bool
    {
        return $this->projectRepository->delete($project->id);
    }
}
