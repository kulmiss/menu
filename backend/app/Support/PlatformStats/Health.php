<?php

namespace App\Support\PlatformStats;

use App\Enums\SubscriptionState;

/**
 * A restaurant's health score, 0–100 (QUESTIONS #186), from four things the
 * app already knows:
 *   setup completeness 30 · admin edits in 30 days 20 · menu views in 30 days 30 · plan status 20
 * Bands: inactive = no menu views, no edits and no team sign-in in 30 days (or the menu is off);
 * healthy = 60 and up; at risk = the rest.
 */
final class Health
{
    public static function score(int $setupPct, int $views30, int $edits30, SubscriptionState $state, bool $paidPlan): int
    {
        $setup = 30 * $setupPct / 100;
        $edits = $edits30 > 0 ? 10 + min(10, $edits30) : 0;
        // 300 views a month or more is full marks; it grows slowly from there down.
        $views = 30 * min(1, log10($views30 + 1) / log10(301));
        $plan = match ($state) {
            SubscriptionState::Active => $paidPlan ? 20 : 12,
            SubscriptionState::Trial => 15,
            SubscriptionState::Grace => 8,
            SubscriptionState::Pending => 5,
            default => 0,
        };

        return (int) round(min(100, $setup + $edits + $views + $plan));
    }

    public static function band(int $score, int $views30, int $edits30, SubscriptionState $state, bool $signedIn30 = false): string
    {
        if (($views30 === 0 && $edits30 === 0 && ! $signedIn30) || in_array($state, [SubscriptionState::Unavailable, SubscriptionState::Suspended], true)) {
            return 'inactive';
        }

        return $score >= 60 ? 'healthy' : 'at_risk';
    }
}
