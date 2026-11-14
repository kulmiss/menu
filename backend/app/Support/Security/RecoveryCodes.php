<?php

namespace App\Support\Security;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Two-factor recovery codes are stored as SHA-256 hashes, like passwords, so a
 * stolen database (even with APP_KEY) gives no working codes (SECURITY_REPORT.md M5).
 * Fortify still writes encrypt(json(list of codes)); User's mutator hands that
 * to store(), which keeps only hashes. The plain codes stay visible for
 * SHOW_MINUTES after they are made (encrypted, in the cache), then never again.
 * Codes are 2 × 10 random characters (~119 bits), so a plain hash is enough.
 */
final class RecoveryCodes
{
    public const SHOW_MINUTES = 15;

    /** The value to store for what Fortify wants to write. */
    public static function store(User $user, ?string $value): ?string
    {
        if ($value === null) {
            Cache::forget(self::cacheKey($user));

            return null;
        }
        $decoded = self::decode($value);
        if (! is_array($decoded) || isset($decoded['sha256'])) {
            return $value; // already hashed (or unknown): keep as it is
        }
        $codes = array_values(array_map('strval', $decoded));
        if ($user->getKey() !== null) {
            Cache::put(self::cacheKey($user), Crypt::encryptString((string) json_encode($codes)), now()->addMinutes(self::SHOW_MINUTES));
        }

        return Crypt::encryptString((string) json_encode(['sha256' => array_map(self::hash(...), $codes)]));
    }

    /** @return list<string> the plain codes, only right after they were made */
    public static function shown(User $user): array
    {
        $cached = Cache::get(self::cacheKey($user));
        try {
            $codes = is_string($cached) ? json_decode(Crypt::decryptString($cached), true) : [];
        } catch (Throwable) {
            $codes = [];
        }

        return is_array($codes) ? array_values(array_map('strval', $codes)) : [];
    }

    /** The code as typed when it is one of the user's unused codes, else null. */
    public static function match(User $user, string $code): ?string
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $hash = self::hash($code);
        foreach (self::hashes($user) as $stored) {
            if (hash_equals($stored, $hash)) {
                return $code;
            }
        }

        return null;
    }

    /** Uses up a code: its hash is removed (no replacement code that nobody could see). */
    public static function consume(User $user, string $code): void
    {
        $hash = self::hash(trim($code));
        $left = array_values(array_filter(self::hashes($user), fn ($h) => ! hash_equals($h, $hash)));
        $user->forceFill(['two_factor_recovery_codes' => Crypt::encryptString((string) json_encode(['sha256' => $left]))])->save();
        if ($user->getKey() !== null) {
            Cache::forget(self::cacheKey($user));
        }
    }

    public static function remaining(User $user): int
    {
        return count(self::hashes($user));
    }

    /** @return list<string> */
    private static function hashes(User $user): array
    {
        $raw = $user->getAttributes()['two_factor_recovery_codes'] ?? null;
        $decoded = is_string($raw) ? self::decode($raw) : null;
        if (! is_array($decoded)) {
            return [];
        }
        if (isset($decoded['sha256']) && is_array($decoded['sha256'])) {
            return array_values(array_map('strval', $decoded['sha256']));
        }

        // Not migrated yet (old plain list): compare by hash all the same.
        return array_values(array_map(fn ($c) => self::hash((string) $c), $decoded));
    }

    /** Our own value (encryptString) or Fortify's (encrypt, serialized). */
    private static function decode(string $value): mixed
    {
        try {
            $plain = Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
        $json = json_decode($plain, true);
        if ($json === null && str_starts_with($plain, 's:')) {
            $json = json_decode((string) @unserialize($plain, ['allowed_classes' => false]), true);
        }

        return $json;
    }

    private static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    private static function cacheKey(User $user): string
    {
        return '2fa-codes-shown:'.$user->getKey();
    }
}
