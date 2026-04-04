<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface TenantRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a tenant by its slug.
     */
    public function findBySlug(string $slug): mixed;

    /**
     * Check if a slug is already taken.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool;

    /**
     * Get tenant with its owner.
     */
    public function findWithOwner(int $id): mixed;
}
