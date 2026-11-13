<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One owner who gets a campaign. Platform model.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property string $email
 * @property string $name
 * @property array<string, string> $vars
 * @property string $status
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property-read Campaign $campaign
 */
#[Fillable(['campaign_id', 'tenant_id', 'user_id', 'email', 'name', 'vars', 'status', 'attempts', 'error', 'sent_at'])]
class CampaignRecipient extends Model
{
    public const MAX_ATTEMPTS = 3;

    protected function casts(): array
    {
        return ['vars' => 'array', 'sent_at' => 'datetime', 'attempts' => 'integer'];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
