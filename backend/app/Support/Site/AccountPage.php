<?php

namespace App\Support\Site;

use App\Support\Auth\GoogleLogin;
use App\Support\BrandColor;
use App\Support\EmailLogo;
use App\Support\Landing\LandingImages;
use App\Support\PlatformSettings;
use App\Support\SignUp\SignUp;
use App\Support\UpgradeContact;

/**
 * What every account page shares with the landing page (QUESTIONS #222): the
 * platform's name, logo and colours, the demo phone pictures, sign-up and
 * Google switches. Texts are in lang/en/account.php.
 */
final class AccountPage
{
    /** @return array<string, mixed> */
    public static function data(): array
    {
        $s = PlatformSettings::all();

        return [
            'platform' => (string) $s['name'],
            'logo' => EmailLogo::url(),
            'colors' => BrandColor::palette($s['brand_color'] ?? null),
            'signupOpen' => (bool) $s['signup_open'],
            'google' => GoogleLogin::enabled(),
            // "Free" only while the sign-up plan is free, like the landing page (QUESTIONS #206).
            'freePlan' => SignUp::plan()?->price_minor === 0,
            'contact' => UpgradeContact::payload(),
            'shots' => ['hero' => LandingImages::phone('hero'), 'hero_dark' => LandingImages::phone('hero_dark')],
        ];
    }

    /**
     * A same-site path to go back to after signing in, or null. Only paths inside
     * the given area, so a link can't send someone elsewhere.
     */
    public static function safeRedirect(?string $to, string $area): ?string
    {
        if (! is_string($to) || $to === '' || ! str_starts_with($to, '/') || str_starts_with($to, '//') || str_contains($to, '\\')) {
            return null;
        }
        $path = (string) parse_url($to, PHP_URL_PATH);

        return $path === $area || str_starts_with($path, $area.'/') ? $to : null;
    }
}
