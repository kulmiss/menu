<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The restaurant's public info (PLAN.md §4.4): about text, contact, Wi-Fi.
 *
 * @property int $id
 * @property array<string, string> $description
 * @property array<string, string> $address
 * @property float|null $lat
 * @property float|null $lng
 * @property string|null $maps_url
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $telegram
 * @property string|null $email
 * @property array<string, string>|null $socials facebook, instagram, tiktok, x, youtube, website → URL
 * @property string|null $wifi_name
 * @property string|null $wifi_password
 * @property bool $show_wifi
 */
#[Fillable(['description', 'address', 'lat', 'lng', 'maps_url', 'phone', 'whatsapp', 'telegram', 'email', 'socials', 'wifi_name', 'wifi_password', 'show_wifi'])]
class TenantProfile extends Model
{
    use BelongsToTenant;

    public const SOCIALS = ['facebook', 'instagram', 'tiktok', 'x', 'youtube', 'website'];

    protected function casts(): array
    {
        return [
            'description' => Translatable::class,
            'address' => Translatable::class,
            'lat' => 'float',
            'lng' => 'float',
            'socials' => 'array',
            'show_wifi' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::first() ?? new self(['show_wifi' => false]);
    }
}
