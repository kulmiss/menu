<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $super_admin_id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $reason
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
#[Fillable(['super_admin_id', 'tenant_id', 'user_id', 'reason', 'started_at', 'ended_at', 'ip_hash'])]
class Impersonation extends Model
{
    public $timestamps = false;

    public const MINUTES = 60;

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }
}
