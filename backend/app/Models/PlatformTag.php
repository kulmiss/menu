<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A Super Admin label for restaurants ("VIP", "At risk"…). Platform only.
 *
 * @property int $id
 * @property string $name
 * @property string $color
 */
#[Fillable(['name', 'color'])]
class PlatformTag extends Model
{
    /** @return BelongsToMany<Tenant, $this> */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'platform_tag_tenant');
    }
}
