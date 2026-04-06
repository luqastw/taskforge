<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Workspace;
use Illuminate\Pagination\LengthAwarePaginator;

interface WorkspaceRepositoryInterface extends RepositoryInterface
{
    /**
     * Get paginated workspaces with filters.
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Search workspaces by name.
     */
    public function search(string $query): LengthAwarePaginator;

    /**
     * Get workspace with projects count.
     */
    public function findWithProjectsCount(int $id): ?Workspace;
}
