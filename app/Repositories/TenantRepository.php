<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class TenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    /**
     * TenantRepository constructor.
     */
    public function __construct(Tenant $model)
    {
        parent::__construct($model);
    }

    /**
     * Find a tenant by its slug.
     */
    public function findBySlug(string $slug): ?Model
    {
        return $this->model->where('slug', $slug)->first();
    }

    /**
     * Check if a slug is already taken.
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = $this->model->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get tenant with its owner.
     */
    public function findWithOwner(int $id): ?Model
    {
        return $this->model->with('users')->find($id);
    }
}
