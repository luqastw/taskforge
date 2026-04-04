<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    /**
     * Find a model by its primary key.
     */
    public function find(int $id): ?Model;

    /**
     * Get all models.
     */
    public function all(): Collection;

    /**
     * Create a new model.
     */
    public function create(array $data): Model;

    /**
     * Update an existing model.
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a model.
     */
    public function delete(int $id): bool;

    /**
     * Find models by a specific column value.
     */
    public function findBy(string $column, mixed $value): Collection;

    /**
     * Find first model by a specific column value.
     */
    public function findOneBy(string $column, mixed $value): ?Model;
}
