<?php

namespace App\Support\Security;

use App\Models\User;
use App\Rules\NotCommonPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Password sign-in for the website and the app (SECURITY_REPORT.md M1, M2).
 *
 * - An unknown e-mail still runs one password hash, so answer times don't tell
 *   which e-mails have an account.
 * - Limits: 5 tries a minute per e-mail + address (route limiter), and 30 WRONG
 *   passwords an hour per account from any address, website and app together
 *   (spread-out guessing). Then the account waits; never longer than the hour,
 *   and right passwords don't count.
 */
final class LoginCheck
{
    public const PER_ACCOUNT_HOUR = 30;

    private static ?string $dummy = null;

    public static function user(string $email, string $password): ?User
    {
        $email = Str::lower(trim($email));
        $key = 'login-acct:'.sha1(Str::transliterate($email));
        if (RateLimiter::tooManyAttempts($key, self::PER_ACCOUNT_HOUR)) {
            throw new ThrottleRequestsException(__('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]), null, [
                'Retry-After' => RateLimiter::availableIn($key),
            ]);
        }

        $user = User::where('email', $email)->first();
        // An unknown e-mail costs the same hash as a wrong password.
        $ok = Hash::check($password, $user !== null ? $user->password : (self::$dummy ??= Hash::make(Str::random(32))));
        if ($user === null || ! $ok) {
            RateLimiter::hit($key, 3600);

            return null;
        }

        // A weak password (older than the M3 list) only gets a gentle banner, never a forced reset.
        $weak = strlen($password) < 10 || NotCommonPassword::isWeak($password, $user->email);
        if ((bool) $user->password_weak !== $weak) {
            $user->forceFill(['password_weak' => $weak])->saveQuietly();
        }

        return $user;
    }

    /** @return list<Limit> */
    public static function limits(string $email, string $ip): array
    {
        $email = Str::transliterate(Str::lower(trim($email)));

        return [Limit::perMinute(5)->by('login:'.$email.'|'.$ip)];
    }
}
