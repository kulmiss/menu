<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property array<string, string> $name
 * @property string $slug
 * @property string $schedule_mode always|scheduled
 * @property bool $is_visible
 * @property int $sort
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, MenuSchedule> $schedules
 */
#[Fillable(['name', 'slug', 'schedule_mode', 'is_visible', 'sort'])]
class Menu extends Model
{
    use BelongsToTenant;

    public const ALWAYS = 'always';

    public const SCHEDULED = 'scheduled';

    protected function casts(): array
    {
        return [
            'name' => Translatable::class,
            'is_visible' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<MenuSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(MenuSchedule::class)->orderBy('weekday')->orderBy('starts_at');
    }
}
