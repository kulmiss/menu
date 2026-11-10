<?php

namespace App\Support;

/**
 * Short keyed hash of an IP address. Lets us spot repeats without storing
 * the address itself (no personal data in logs or stats).
 */
final class IpHash
{
    public static function make(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 16);
    }
}
