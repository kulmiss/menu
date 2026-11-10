<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type special|announcement
 * @property array<string, string> $title
 * @property array<string, string> $body
 * @property int|null $media_id
 * @property int|null $item_id
 * @property int|null $branch_id null = every branch
 * @property Carbon|null $starts_at restaurant local time
 * @property Carbon|null $ends_at restaurant local time
 * @property bool $is_active
 * @property int $sort
 * @property-read Media|null $media
 */
#[Fillable(['type', 'title', 'body', 'media_id', 'item_id', 'branch_id', 'starts_at', 'ends_at', 'is_active', 'sort'])]
class Promotion extends Model
{
    use BelongsToTenant;

    public const TYPES = ['special', 'announcement'];

    protected function casts(): array
    {
        return [
            'title' => Translatable::class,
            'body' => Translatable::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
