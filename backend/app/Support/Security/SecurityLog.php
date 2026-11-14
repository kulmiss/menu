<?php

namespace App\Support\Security;

use App\Support\IpHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One line per security event in storage/logs/security.log, kept 30 days
 * (SECURITY_REPORT.md M11). No passwords, tokens, codes or e-mail addresses:
 * only the user id (when known), a keyed hash of the e-mail and of the IP, so
 * repeats can be spotted without storing personal data. "Log in as" is not
 * recorded here either (owner's choice).
 */
final class SecurityLog
{
    public const EVENTS = [
        'login.failed', 'login.locked', 'two_factor.failed', 'password.reset_requested', 'password.reset',
        'password.changed', 'email_change.requested', 'email_change.confirmed', 'tokens.revoked',
        'signup.blocked', 'upload.refused', 'outbound.refused',
    ];

    /** @param  array<string, scalar|null>  $context */
    public static function event(string $event, ?int $userId = null, ?string $email = null, array $context = []): void
    {
        $request = app()->bound('request') ? request() : null;
        Log::channel('security')->info($event, array_filter([
            'user' => $userId,
            'email' => $email !== null && $email !== '' ? self::hash(Str::lower(trim($email))) : null,
            'ip' => IpHash::make($request?->ip()),
            'app' => $request?->is('api/v1/mobile/*') ? true : null,
            ...$context,
        ], fn ($v) => $v !== null));
    }

    private static function hash(string $value): string
    {
        return substr(hash_hmac('sha256', 'email|'.$value, (string) config('app.key')), 0, 16);
    }
}
