<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = tenant_id();

        if ($tenantId !== null) {
            $builder->where("{$model->getTable()}.tenant_id", $tenantId);
        }
    }
}
