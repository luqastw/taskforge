<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * UserRepository constructor.
     */
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): ?Model
    {
        return $this->model->withoutGlobalScope(TenantScope::class)
            ->where('email', $email)
            ->first();
    }

    /**
     * Find a user by email within a specific tenant.
     */
    public function findByEmailAndTenant(string $email, int $tenantId): ?Model
    {
        return $this->model->withoutGlobalScope(TenantScope::class)
            ->where('email', $email)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Get users by tenant.
     */
    public function findByTenant(int $tenantId): Collection
    {
        return $this->model->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->get();
    }
}
