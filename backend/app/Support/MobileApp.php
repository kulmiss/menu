<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Super Admin → Mobile app (owner, 2026-11-14, QUESTIONS #193): who may sign
 * in to the app, maintenance, versions, the in-app banner, app-only switches
 * and links. Stored as one platform setting (`mobile`), merged with the
 * defaults below; read by `GET mobile/config` and the MobileAppGate.
 */
final class MobileApp
{
    public const APP_FEATURES = ['biometric_lock', 'push', 'analytics', 'crash_reports'];

    public const AUDIENCES = ['all', 'restaurant', 'super_admin'];

    public const DEFAULTS = [
        'restaurant_sign_in' => true,
        'super_admin_sign_in' => true,
        'sign_in_message' => '',
        'maintenance' => false,
        'maintenance_message' => '',
        'latest_version' => '1.0.0',
        'release_notes' => '',
        'min_version' => '1.0.0',
        'recommended_version' => '',
        'banner' => ['text' => '', 'link' => '', 'starts_at' => null, 'ends_at' => null, 'audience' => 'all'],
        'app_features' => ['biometric_lock' => true, 'push' => true, 'analytics' => true, 'crash_reports' => true],
        'links' => ['play_store' => '', 'app_store' => '', 'privacy' => '', 'terms' => '', 'support_whatsapp' => ''],
    ];

    /** @return array<string, mixed> */
    public static function settings(): array
    {
        $stored = PlatformSettings::get('mobile');
        $stored = is_array($stored) ? $stored : [];
        $out = array_replace(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        foreach (['banner', 'app_features', 'links'] as $nested) {
            $out[$nested] = array_replace(self::DEFAULTS[$nested], array_intersect_key(is_array($stored[$nested] ?? null) ? $stored[$nested] : [], self::DEFAULTS[$nested]));
        }

        return $out;
    }

    /** @param array<string, mixed> $values */
    public static function save(array $values): void
    {
        $next = self::settings();
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            $next[$key] = is_array(self::DEFAULTS[$key]) && is_array($value) ? array_replace($next[$key], array_intersect_key($value, self::DEFAULTS[$key])) : $value;
        }
        PlatformSettings::save(['mobile' => $next]);
    }

    public static function signInAllowed(bool $superAdmin): bool
    {
        $s = self::settings();

        return (bool) ($superAdmin ? $s['super_admin_sign_in'] : $s['restaurant_sign_in']);
    }

    /**
     * The banner while it is between its dates (null when empty or outside them).
     *
     * @return array{text: string, link: string|null, audience: string, ends_at: string|null}|null
     */
    public static function activeBanner(?CarbonImmutable $now = null): ?array
    {
        $b = self::settings()['banner'];
        $now ??= CarbonImmutable::now();
        if (trim((string) $b['text']) === '') {
            return null;
        }
        if ($b['starts_at'] && $now->lt(CarbonImmutable::parse($b['starts_at']))) {
            return null;
        }
        if ($b['ends_at'] && $now->gte(CarbonImmutable::parse($b['ends_at']))) {
            return null;
        }

        return ['text' => (string) $b['text'], 'link' => $b['link'] ?: null, 'audience' => (string) $b['audience'], 'ends_at' => $b['ends_at']];
    }

    public static function appFeature(string $key): bool
    {
        return (bool) (self::settings()['app_features'][$key] ?? true);
    }

    /** x.y.z comparison; build numbers and anything after "+" are ignored. */
    public static function older(string $version, string $than): bool
    {
        $parts = fn (string $v) => array_pad(array_map('intval', explode('.', explode('+', $v)[0])), 3, 0);

        return $than !== '' && $parts($version) < $parts($than);
    }
}
