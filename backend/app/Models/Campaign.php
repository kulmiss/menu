<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A news e-mail to restaurant owners (Super Admin → Messages). Platform model.
 *
 * @property int $id
 * @property string $subject
 * @property string $body Markdown-lite: **bold**, [links](https://…), "- " lists, blank lines
 * @property array<string, mixed> $filters
 * @property string $status
 * @property int $recipients_count
 * @property int|null $created_by
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
#[Fillable(['subject', 'body', 'filters', 'status', 'recipients_count', 'created_by', 'finished_at'])]
class Campaign extends Model
{
    protected function casts(): array
    {
        return ['filters' => 'array', 'finished_at' => 'datetime'];
    }

    /** @return HasMany<CampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
