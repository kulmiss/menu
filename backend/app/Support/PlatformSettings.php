<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide settings (PLAN.md §7.3 screen 8), cached; defaults below.
 */
class PlatformSettings
{
    public const DEFAULTS = [
        'name' => 'Menu',
        'support_email' => null,
        'domain' => null,
        'default_locale' => 'en',
        'default_currency' => 'ETB',
        'terms' => '',
        'privacy' => '',
        // Default link preview when a restaurant has no banner (path on the public disk; null = built-in).
        'share_image' => null,
        // E-mail header logo (path on the public disk; null = the platform name in text).
        'logo' => null,
        // Self sign-up (owner, 2026-11-13).
        'signup_open' => true,
        'signup_plan_id' => null, // null = the "free" plan
        'signup_approval' => false,
        'signup_notify' => true,
        'turnstile_enabled' => false,
        'turnstile_site_key' => '',
        'turnstile_secret' => '', // encrypted (Crypt), never sent to the browser
        'disposable_domains' => [
            '10minutemail.com', 'dispostable.com', 'getnada.com', 'guerrillamail.com', 'mailinator.com', 'maildrop.cc',
            'sharklasers.com', 'temp-mail.org', 'tempmail.com', 'throwawaymail.com', 'trashmail.com', 'yopmail.com',
        ],
        // "Upgrade" hint when a plan limit is reached (payments are manual).
        'upgrade_whatsapp' => '',
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return Cache::remember('platform_settings', 3600, function () {
            $stored = DB::table('platform_settings')->pluck('value', 'key')
                ->map(fn ($v) => json_decode((string) $v, true))->all();

            return [...self::DEFAULTS, ...array_intersect_key($stored, self::DEFAULTS)];
        });
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Settings for the Super Admin screen: secrets replaced by "is set".
     *
     * @return array<string, mixed>
     */
    public static function forScreen(): array
    {
        $all = self::all();
        $all['turnstile_secret_set'] = ($all['turnstile_secret'] ?? '') !== '';
        unset($all['turnstile_secret']);

        return $all;
    }

    /** The Turnstile secret key, decrypted (empty when not set). */
    public static function turnstileSecret(): string
    {
        $stored = (string) self::get('turnstile_secret');
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return '';
        }
    }

    /** @param array<string, mixed> $values */
    public static function save(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            DB::table('platform_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        }
        Cache::forget('platform_settings');
    }
}
