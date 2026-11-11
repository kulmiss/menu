<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * SMTP settings from Super Admin → Settings → Email (owner, 2026-11-11).
 * Stored in platform_settings under "mail"; the password is encrypted with
 * the app key and never sent back. With no host saved, the server's .env
 * MAIL_* settings are used.
 */
class MailSettings
{
    public const ENCRYPTIONS = ['none', 'ssl', 'tls'];

    private const KEY = 'mail';

    private const CACHE = 'platform_mail_settings';

    /** @return array{host?: string, port?: int, encryption?: string, username?: string|null, password?: string|null, from_address?: string|null, from_name?: string|null}|null */
    public static function stored(): ?array
    {
        try {
            /** @var array<string, mixed>|null $value */
            $value = Cache::rememberForever(self::CACHE, function () {
                $raw = DB::table('platform_settings')->where('key', self::KEY)->value('value');

                return $raw === null ? null : json_decode((string) $raw, true);
            });
        } catch (Throwable) {
            return null; // no database yet (install, some artisan commands)
        }

        return is_array($value) && ($value['host'] ?? '') !== '' ? $value : null;
    }

    /**
     * What the settings screen shows: never the password itself.
     *
     * @return array<string, mixed>
     */
    public static function forScreen(): array
    {
        $s = self::stored();

        return [
            'source' => $s ? 'platform' : 'env',
            'host' => $s['host'] ?? '',
            'port' => $s['port'] ?? 587,
            'encryption' => $s['encryption'] ?? 'tls',
            'username' => $s['username'] ?? '',
            'has_password' => ($s['password'] ?? null) !== null,
            'from_address' => $s['from_address'] ?? '',
            'from_name' => $s['from_name'] ?? '',
            // What is used while nothing is saved here (host only, no secrets).
            'env_host' => (string) config('mail.mailers.smtp.host'),
            'env_mailer' => (string) config('mail.default'),
        ];
    }

    /**
     * @param  array<string, mixed>  $values  host, port, encryption, username, from_address, from_name
     * @param  string|null  $password  null keeps the saved one
     */
    public static function save(array $values, ?string $password, bool $clearPassword = false): void
    {
        $current = self::stored() ?? [];
        $stored = [
            'host' => (string) $values['host'],
            'port' => (int) $values['port'],
            'encryption' => (string) $values['encryption'],
            'username' => ($values['username'] ?? '') === '' ? null : (string) $values['username'],
            'password' => $clearPassword ? null : ($password !== null && $password !== '' ? Crypt::encryptString($password) : ($current['password'] ?? null)),
            'from_address' => ($values['from_address'] ?? '') === '' ? null : (string) $values['from_address'],
            'from_name' => ($values['from_name'] ?? '') === '' ? null : (string) $values['from_name'],
        ];
        DB::table('platform_settings')->updateOrInsert(['key' => self::KEY], ['value' => json_encode($stored), 'updated_at' => now(), 'created_at' => now()]);
        self::flush();
    }

    /** Back to the .env settings. */
    public static function clear(): void
    {
        DB::table('platform_settings')->where('key', self::KEY)->delete();
        self::flush();
    }

    /**
     * Points Laravel's mailer at the saved SMTP server (called when the mail
     * manager is created, so every e-mail of the platform uses it).
     */
    public static function apply(): void
    {
        $s = self::stored();
        if ($s === null) {
            return;
        }
        $password = null;
        if (($s['password'] ?? null) !== null) {
            try {
                $password = Crypt::decryptString((string) $s['password']);
            } catch (Throwable) {
                $password = null; // the app key changed: the password must be entered again
            }
        }
        $encryption = $s['encryption'] ?? 'tls';
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                ...(array) config('mail.mailers.smtp'),
                'transport' => 'smtp',
                'url' => null,
                'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'host' => $s['host'],
                'port' => (int) ($s['port'] ?? 587),
                'username' => $s['username'] ?? null,
                'password' => $password,
                // TLS: STARTTLS is required; none: plain connection only.
                'require_tls' => $encryption === 'tls',
                'auto_tls' => $encryption !== 'none',
                'timeout' => 15,
            ],
        ]);
        if (($s['from_address'] ?? null) !== null) {
            config(['mail.from.address' => $s['from_address']]);
        }
        if (($s['from_name'] ?? null) !== null) {
            config(['mail.from.name' => $s['from_name']]);
        }
    }

    private static function flush(): void
    {
        Cache::forget(self::CACHE);
        // This process may already hold a mailer built with the old settings.
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
            self::apply();
        }
    }
}
