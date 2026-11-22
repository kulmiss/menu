<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A real restaurant shown on Landing 2 (owner, 2026-11-22): platform-wide, not a tenant's
 * data. Only with "permission" ticked; the quotes must be the restaurant's real words.
 *
 * @property int $id
 * @property string $name
 * @property string|null $city
 * @property string|null $quote_en
 * @property string|null $quote_am
 * @property string|null $logo
 * @property bool $permission
 * @property bool $is_active
 * @property int $sort
 */
class LandingRestaurant extends Model
{
    protected $fillable = ['name', 'city', 'quote_en', 'quote_am', 'permission', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['permission' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    public const LOGO_FOLDER = 'landing/restaurants';

    /**
     * The logo at 64 px (1x) and 128 px (2x), or null.
     *
     * @return array{src: string, srcset: string}|null
     */
    public function logoUrl(): ?array
    {
        if ($this->logo === null) {
            return null;
        }
        $disk = Storage::disk('public');

        return ['src' => $disk->url("{$this->logo}/64.webp"), 'srcset' => $disk->url("{$this->logo}/64.webp").' 1x, '.$disk->url("{$this->logo}/128.webp").' 2x'];
    }

    /** @return array<string, mixed> */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'city' => $this->city, 'quote_en' => $this->quote_en, 'quote_am' => $this->quote_am,
            'logo' => $this->logo === null ? null : Storage::disk('public')->url("{$this->logo}/128.webp"),
            'permission' => $this->permission, 'is_active' => $this->is_active, 'sort' => $this->sort,
        ];
    }
}
