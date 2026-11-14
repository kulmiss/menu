<?php

namespace App\Support\Push;

use App\Models\User;

/** Which pushes exist, who gets them, and each user's on/off switches (QUESTIONS #189). */
final class PushTypes
{
    public const RESTAURANT = ['plan_expiring', 'plan_expired', 'monthly_report', 'invitation_accepted', 'platform_announcement'];

    public const PLATFORM = ['signup_new', 'signup_pending', 'payment_recorded', 'backup_failed', 'health_warning', 'followups_due'];

    /** @return list<string> */
    public static function for(User $user): array
    {
        $types = $user->memberships()->exists() ? self::RESTAURANT : [];

        return $user->is_super_admin ? [...$types, ...self::PLATFORM] : $types;
    }

    /** Every type is on unless the user switched it off. */
    public static function enabled(User $user, string $type): bool
    {
        $settings = is_array($user->push_settings) ? $user->push_settings : [];

        return ($settings[$type] ?? true) !== false;
    }
}
