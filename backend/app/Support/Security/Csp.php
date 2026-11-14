<?php

namespace App\Support\Security;

use App\Support\PublicMenu\InlineAssets;
use Illuminate\Support\Str;

/**
 * Content-Security-Policy pieces (SECURITY_REPORT.md H1).
 *
 * - Admin and site pages: scripts only from this origin (plus Cloudflare
 *   Turnstile on sign-up) and inline scripts with a per-request nonce.
 * - Guest menu: its one inline script is allowed by hash, in a <meta> tag in
 *   the page itself, so the static copy (served without PHP) carries it too.
 *   Styles stay 'unsafe-inline' (style attributes for photo placeholders; CSS
 *   cannot run code). The page may still be embedded on a restaurant's site.
 */
final class Csp
{
    private const TURNSTILE = 'https://challenges.cloudflare.com';

    /** A fresh nonce per request (no Octane here, so a request-scoped instance is enough). */
    public static function nonce(): string
    {
        $app = app();
        if (! $app->bound('csp.nonce')) {
            $app->instance('csp.nonce', Str::random(32));
        }

        return (string) $app->make('csp.nonce');
    }

    /** True when a page asked for a nonce, i.e. it has inline scripts. */
    public static function nonceUsed(): bool
    {
        return app()->bound('csp.nonce');
    }

    public static function html(): string
    {
        $nonce = self::nonceUsed() ? " 'nonce-".self::nonce()."'" : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'".$nonce.' '.self::TURNSTILE,
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'self' ".self::TURNSTILE,
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /** API answers: nothing may load and nothing may frame them. */
    public static function api(): string
    {
        return "default-src 'none'; frame-ancestors 'none'";
    }

    /** The guest page's own policy, written into its <head>. */
    public static function menu(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src '".self::menuScriptHash()."'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "worker-src 'self'",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
        ]);
    }

    public static function menuScriptHash(): string
    {
        return 'sha256-'.base64_encode(hash('sha256', InlineAssets::js(), true));
    }
}
