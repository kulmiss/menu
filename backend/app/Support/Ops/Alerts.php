<?php

namespace App\Support\Ops;

use App\Models\User;
use App\Notifications\ServerAlertNotification;
use App\Support\PlatformSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Alerts to the Super Admins (owner, 2026-11-19, QUESTIONS #233): server
 * errors and failed go-live checks, by e-mail (switch "alerts_email") and to a
 * Telegram chat (bot token + chat id, behind "alerts_telegram_enabled").
 *
 * Errors are grouped: the first one sends at once (after the response), then
 * at most one alert per 30 minutes says how many more happened. An alert that
 * fails to send is never logged as an error itself (no loop).
 */
final class Alerts
{
    public const ERROR_EVERY_MINUTES = 30;

    private static bool $sending = false;

    /** Log listener: errors and worse become an alert. */
    public static function onLog(MessageLogged $event): void
    {
        if (self::$sending || ! in_array($event->level, ['error', 'critical', 'alert', 'emergency'], true)) {
            return;
        }
        try {
            $count = (int) Cache::increment('alerts:errors:count');
            if (! Cache::add('alerts:errors:sent', true, now()->addMinutes(self::ERROR_EVERY_MINUTES))) {
                return; // grouped into the next alert
            }
            $message = self::clean($event->message);
            $more = max(0, $count - 1);
            Cache::put('alerts:errors:count', 0);
            $send = fn () => self::send(
                (string) __('alerts.error_subject', ['platform' => PlatformSettings::get('name')]),
                trim($message."\n".($more > 0 ? __('alerts.error_more', ['count' => $more]) : '')),
            );
            app()->runningInConsole() ? $send() : app()->terminating($send);
        } catch (Throwable) {
            // The cache or settings are down too: nothing more can be done safely here.
        }
    }

    /**
     * E-mail and/or Telegram, as switched on. Returns what went out.
     *
     * @return array{email: int, telegram: bool}
     */
    public static function send(string $subject, string $text): array
    {
        $sent = ['email' => 0, 'telegram' => false];
        self::$sending = true;
        try {
            if (PlatformSettings::get('alerts_email')) {
                $admins = User::where('is_super_admin', true)->get();
                Notification::sendNow($admins, new ServerAlertNotification($subject, $text));
                $sent['email'] = $admins->count();
            }
            $sent['telegram'] = self::telegram($subject."\n\n".$text);
        } catch (Throwable) {
            // Never log: logging an error would raise another alert.
        } finally {
            self::$sending = false;
        }

        return $sent;
    }

    /** To the Telegram chat, when switched on and complete. */
    public static function telegram(string $text): bool
    {
        $s = PlatformSettings::all();
        $token = self::token();
        if (! $s['alerts_telegram_enabled'] || $token === '' || trim((string) $s['alerts_telegram_chat']) === '') {
            return false;
        }
        try {
            return Http::timeout(5)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => trim((string) $s['alerts_telegram_chat']),
                'text' => mb_substr($text, 0, 3500),
                'disable_web_page_preview' => 'true',
            ])->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public static function token(): string
    {
        $stored = (string) PlatformSettings::get('alerts_telegram_token');
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return '';
        }
    }

    /** The first line of an error, without anything that looks like a secret. */
    public static function clean(string $message): string
    {
        $line = strtok($message, "\n") ?: $message;
        $line = (string) preg_replace('/(password|secret|token|key)(["\']?\s*[=:]\s*)\S+/i', '$1$2***', $line);
        $line = (string) preg_replace('#bot\d+:[A-Za-z0-9_-]+#', 'bot***', $line);

        return mb_substr($line, 0, 500);
    }
}
