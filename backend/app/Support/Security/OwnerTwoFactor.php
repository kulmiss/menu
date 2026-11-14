<?php

namespace App\Support\Security;

use App\Enums\Role;
use App\Models\User;
use App\Support\PlatformSettings;

/**
 * Two-factor sign-in for restaurant Owners (owner, 2026-11-14; SECURITY_REPORT.md M17):
 * strongly encouraged (dashboard card, one-tap setup, one reminder e-mail after
 * 7 days). The Super Admin can make it required; it starts off.
 */
final class OwnerTwoFactor
{
    public static function isOwner(User $user): bool
    {
        return $user->memberships()->where('role', Role::Owner->value)->whereHas('tenant')->exists();
    }

    /** True when this user must set up two-factor before using a restaurant. */
    public static function required(User $user): bool
    {
        return $user->two_factor_confirmed_at === null
            && ! $user->is_super_admin
            && (bool) PlatformSettings::get('owner_two_factor_required')
            && self::isOwner($user);
    }
}
