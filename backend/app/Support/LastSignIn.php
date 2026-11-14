<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Keeps users.last_login_at: the time of the user's latest sign-in, nothing
 * more (no history, no IP). Used by the Super Admin dashboard's "active"
 * numbers (owner, 2026-11-13, QUESTIONS #187). "Log in as" and switching back
 * are not sign-ins of the user and run quietly.
 */
final class LastSignIn
{
    private static bool $quiet = false;

    public static function record(mixed $user): void
    {
        if (self::$quiet || ! $user instanceof User) {
            return;
        }
        $now = now();
        // Straight to the table: no updated_at change, no model events.
        DB::table('users')->where('id', $user->id)->update(['last_login_at' => $now]);
        $user->setRawAttributes(['last_login_at' => $now] + $user->getAttributes(), true);
    }

    /** Runs $callback without recording sign-ins. */
    public static function quietly(callable $callback): mixed
    {
        $before = self::$quiet;
        self::$quiet = true;
        try {
            return $callback();
        } finally {
            self::$quiet = $before;
        }
    }
}
