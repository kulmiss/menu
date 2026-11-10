<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
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
