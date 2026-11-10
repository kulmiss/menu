<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            throw MissingTenantException::make($model::class);
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
