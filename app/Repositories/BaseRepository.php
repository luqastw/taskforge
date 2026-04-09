<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

abstract class BaseRepository implements RepositoryInterface
{
    protected Model $model;

    protected int $cacheTTL = 600; // 10 minutes

    /**
     * BaseRepository constructor.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function find(int $id): ?Model
    {
        return Cache::remember(
            $this->getCacheKey($id),
            $this->cacheTTL,
            fn () => $this->model->find($id)
        );
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function create(array $data): Model
    {
        $model = $this->model->create($data);
        $this->clearCache($model);

        return $model;
    }

    public function update(int $id, array $data): bool
    {
        $model = $this->find($id);

        if ($model === null) {
            return false;
        }

        $model->update($data);
        $this->clearCache($model);

        return true;
    }

    public function delete(int $id): bool
    {
        $model = $this->find($id);

        if ($model === null) {
            return false;
        }

        $this->clearCache($model);

        return $model->delete();
    }

    public function findBy(string $column, mixed $value): Collection
    {
        return $this->model->where($column, $value)->get();
    }

    public function findOneBy(string $column, mixed $value): ?Model
    {
        return $this->model->where($column, $value)->first();
    }

    /**
     * Get cache key for a specific model instance.
     */
    protected function getCacheKey(int|Model $id): string
    {
        $modelClass = class_basename($this->model);
        $modelId = $id instanceof Model ? $id->id : $id;
        $tenantId = tenant_id() ?? 0;

        return "tenant.{$tenantId}.".strtolower($modelClass).'.'.$modelId;
    }

    /**
     * Clear cache for a specific model.
     */
    protected function clearCache(Model $model): void
    {
        Cache::forget($this->getCacheKey($model));
    }
}
