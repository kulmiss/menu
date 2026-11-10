<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $actor_id
 * @property int|null $impersonator_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $changes
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read User|null $actor
 *
 * Append-only record of important changes. tenant_id is null for
 * platform-level (Super Admin) events.
 */
#[Fillable(['tenant_id', 'actor_id', 'impersonator_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip_hash'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
