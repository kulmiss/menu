<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * A signed-in device of the mobile app (QUESTIONS #187).
 *
 * @property string|null $platform android | ios
 * @property string|null $app_version
 * @property int|null $impersonator_id Super Admin who opened "log in as" (QUESTIONS #188)
 * @property int|null $impersonation_tenant_id
 * @property Carbon|null $last_used_at
 */
class PersonalAccessToken extends SanctumToken
{
    /** Days a token may stay unused before it stops working (sliding expiry). */
    public const IDLE_DAYS = 60;

    public const IDLE_DAYS_SUPER_ADMIN = 14;

    /** Hard limit from sign-in, whatever the use. */
    public const MAX_DAYS = 365;

    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'platform', 'app_version'];

    public function isImpersonation(): bool
    {
        return $this->impersonator_id !== null;
    }

    /** Still valid by the idle rule? (expires_at is checked by Sanctum.) */
    public function isFresh(?User $owner = null): bool
    {
        $user = $owner ?? $this->tokenable;
        $days = $user instanceof User && $user->is_super_admin ? self::IDLE_DAYS_SUPER_ADMIN : self::IDLE_DAYS;
        $last = $this->last_used_at ?? $this->created_at;

        return $last !== null && $last->greaterThan(now()->subDays($days));
    }
}
