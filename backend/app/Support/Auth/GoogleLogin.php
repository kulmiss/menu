<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PlatformSettings;
use App\Support\Security\SecurityLog;
use App\Support\SignUp\SignUp;
use App\Support\SignUp\SlugMaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Continue with Google" for restaurant people, on the website and in the
 * app (owner, 2026-11-17; docs/google-login.md). The same rules everywhere:
 *
 * - Only a Google account whose e-mail Google has verified.
 * - A linked Google account signs its user in. An existing account with the
 *   same e-mail is linked automatically ("Google is now linked").
 * - Two-factor users still type their code. Super Admins never use Google.
 * - A new e-mail creates a restaurant on the sign-up plan, after the person
 *   gives the restaurant name and accepts the Terms — only while sign-up is
 *   open; with approval on, the restaurant waits like any other sign-up.
 * - Switched off (Super Admin, or no keys in .env): no buttons, no routes.
 */
class GoogleLogin
{
    /** How long a Google sign-up waits for the restaurant name. */
    public const PENDING_MINUTES = 15;

    public function __construct(private readonly SignUp $signUp, private readonly ActivityLogger $activity) {}

    public static function enabled(): bool
    {
        return (bool) PlatformSettings::get('google_login') && self::configured();
    }

    /** The keys are in .env (Super Admin → Settings → Sign-up shows it). */
    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * What to do with a Google account: sign in this user (linked now if it
     * wasn't), create a new restaurant, or refuse.
     *
     * @param  array{sub: string, email: string, email_verified: bool, name: string}  $google
     * @return array{result: 'user', user: User, linked: bool}|array{result: 'new'}|array{result: 'refused', why: string}
     */
    public function resolve(array $google): array
    {
        $email = Str::lower(trim($google['email']));
        if (! $google['email_verified'] || $email === '' || $google['sub'] === '') {
            SecurityLog::event('google.refused', null, $email, ['why' => 'unverified']);

            return ['result' => 'refused', 'why' => 'unverified'];
        }

        $user = User::where('google_id', $google['sub'])->first() ?? User::where('email', $email)->first();
        if ($user === null) {
            return ['result' => 'new'];
        }
        if ($user->is_super_admin) {
            SecurityLog::event('google.refused', $user->id, $email, ['why' => 'super_admin']);

            return ['result' => 'refused', 'why' => 'super_admin'];
        }

        $linked = false;
        if ($user->google_id === null) {
            $this->link($user, $google);
            $linked = true;
        }
        // Google has just shown that this person owns the address.
        if (! $user->hasVerifiedEmail() && Str::lower($user->email) === $email) {
            $user->markEmailAsVerified();
            $this->signUp->verified($user);
        }

        return ['result' => 'user', 'user' => $user, 'linked' => $linked];
    }

    /** @param  array{sub: string, email: string, email_verified: bool, name: string}  $google */
    public function link(User $user, array $google): void
    {
        $user->forceFill(['google_id' => $google['sub'], 'google_email' => Str::lower($google['email'])])->save();
        SecurityLog::event('google.linked', $user->id, $user->email);
        $this->activity->log('account.google_linked', $user, null, null);
    }

    public function unlink(User $user): void
    {
        $user->forceFill(['google_id' => null, 'google_email' => null])->save();
        SecurityLog::event('google.unlinked', $user->id, $user->email);
        $this->activity->log('account.google_unlinked', $user, null, null);
    }

    /** Signed in with Google (website or app); the token or code is never logged. */
    public static function signedIn(User $user, bool $app): void
    {
        SecurityLog::event('login.google', $user->id, null, $app ? ['app' => true] : []);
    }

    /**
     * A new owner from Google: e-mail already verified, no password (until
     * they set one), a restaurant on the sign-up plan. Approval on → it waits.
     *
     * @param  array{sub: string, email: string, email_verified: bool, name: string}  $google
     */
    public function createOwner(array $google, string $restaurant, string $locale): User
    {
        return DB::transaction(function () use ($google, $restaurant, $locale) {
            $email = Str::lower(trim($google['email']));
            $user = $this->signUp->create([
                'name' => Str::limit(trim($google['name']) !== '' ? trim($google['name']) : Str::before($email, '@'), 120, ''),
                'email' => $email,
                'password' => null,
                'restaurant_name' => $restaurant,
                'slug' => SlugMaker::from($restaurant),
                'currency' => SignUp::currency(),
                'locale' => $locale,
            ]);
            $user->forceFill(['google_id' => $google['sub'], 'google_email' => $email, 'email_verified_at' => now()])->save();
            SecurityLog::event('google.signup', $user->id, $email);
            $this->signUp->verified($user);

            return $user;
        });
    }

    /**
     * Google's answer as the four things we use.
     *
     * @param  array<string, mixed>  $raw
     * @return array{sub: string, email: string, email_verified: bool, name: string}
     */
    public static function profile(array $raw): array
    {
        $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        return [
            'sub' => (string) ($raw['sub'] ?? $raw['id'] ?? ''),
            'email' => (string) ($raw['email'] ?? ''),
            // Google sends true, or "true" in some ID tokens.
            'email_verified' => $verified === true || $verified === 'true',
            'name' => (string) ($raw['name'] ?? ''),
        ];
    }
}
