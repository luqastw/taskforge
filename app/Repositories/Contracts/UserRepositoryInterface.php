<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): mixed;

    /**
     * Find a user by email within a specific tenant.
     */
    public function findByEmailAndTenant(string $email, int $tenantId): mixed;

    /**
     * Get users by tenant.
     */
    public function findByTenant(int $tenantId): mixed;
}
