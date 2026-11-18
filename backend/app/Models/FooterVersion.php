<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One published version of the website footer (App\Support\Site\Footer);
 * the newest one is live.
 *
 * @property int $id
 * @property array<string, mixed> $content
 * @property int|null $published_by
 * @property int|null $restored_from
 * @property Carbon $created_at
 * @property-read User|null $publisher
 */
#[Fillable(['content', 'published_by', 'restored_from'])]
class FooterVersion extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
