<?php

namespace App\Support\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks a Google ID token sent by the Menu Admin app (owner, 2026-11-17):
 * signed by Google (its public keys, cached as long as Google says), issued
 * by accounts.google.com, for our web client (the app asks Google for a
 * token for it), from one of our Android clients when they are listed, and
 * not expired. The token itself is never stored or logged.
 */
class GoogleIdToken
{
    public const CERTS = 'https://www.googleapis.com/oauth2/v3/certs';

    private const CACHE = 'google:id-token-keys';

    /**
     * @return array{sub: string, email: string, email_verified: bool, name: string}|null null when the token is not valid
     */
    public static function verify(string $token): ?array
    {
        $clientId = (string) config('services.google.client_id');
        if ($clientId === '' || substr_count($token, '.') !== 2) {
            return null;
        }
        try {
            JWT::$leeway = 60;
            $claims = (array) JWT::decode($token, JWK::parseKeySet(self::keys()));
        } catch (Throwable) {
            return null;
        }
        $android = (array) config('services.google.android_client_ids');
        $ok = in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && ($claims['aud'] ?? null) === $clientId
            && ($android === [] || in_array($claims['azp'] ?? null, $android, true));

        return $ok ? GoogleLogin::profile($claims) : null;
    }

    /** @return array{keys: list<array<string, mixed>>} */
    private static function keys(): array
    {
        $cached = Cache::get(self::CACHE);
        if (is_array($cached)) {
            return $cached;
        }
        $res = Http::timeout(5)->retry(1, 200)->get(self::CERTS)->throw();
        $keys = $res->json();
        preg_match('/max-age=(\d+)/', (string) $res->header('Cache-Control'), $m);
        Cache::put(self::CACHE, $keys, now()->addSeconds(max(300, min(86400, (int) ($m[1] ?? 3600)))));

        return $keys;
    }
}
