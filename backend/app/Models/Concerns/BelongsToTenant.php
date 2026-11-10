<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use App\Tenancy\MissingTenantException;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every restaurant-owned model: queries only see the current tenant's
 * rows, new rows get the current tenant_id, and nothing works without a
 * current tenant. Only code in App\Platform may call withoutTenancy().
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $current = app(CurrentTenant::class)->id();

            if ($current === null) {
                throw MissingTenantException::make($model::class);
            }

            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $current);
            } elseif ((int) $model->getAttribute('tenant_id') !== $current) {
                throw new MissingTenantException('Refusing to create a row for another tenant.');
            }
        });
    }

    /**
     * @return Builder<static>
     */
    public static function withoutTenancy(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
