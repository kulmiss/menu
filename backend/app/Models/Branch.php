<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Qr\QrMap;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A location of the restaurant. The default branch is the menu at /{slug};
 * others are at /{slug}/{branch}.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $slug
 * @property array<string, string> $name
 * @property array<string, string> $address
 * @property string|null $phone
 * @property string|null $maps_url
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort
 * @property-read Collection<int, DiningTable> $tables
 */
#[Fillable(['slug', 'name', 'address', 'phone', 'maps_url', 'is_default', 'is_active', 'sort'])]
class Branch extends Model
{
    protected static function booted(): void
    {
        // /q/{code} is answered from a file: keep it in step.
        static::saved(fn () => QrMap::touch());
        static::deleted(fn () => QrMap::touch());
    }

    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'address' => Translatable::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return HasMany<DiningTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('sort')->orderBy('id');
    }

    /** Path of the guest page for this branch. */
    public function path(Tenant $tenant): string
    {
        return '/'.$tenant->slug.($this->is_default ? '' : '/'.$this->slug);
    }
}
