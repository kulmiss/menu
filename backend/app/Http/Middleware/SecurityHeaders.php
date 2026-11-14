<?php

namespace App\Http\Middleware;

use App\Support\Security\Csp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Security headers on every Laravel answer (SECURITY_REPORT.md H1, M8). Static
 * files get the same from public/.htaccess or the Nginx example. A header a
 * controller already set wins (e.g. the appearance preview, framed by the admin).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->forgetInstance('csp.nonce');
        /** @var Response $response */
        $response = $next($request);
        $h = $response->headers;

        $set = function (string $name, string $value) use ($h): void {
            if (! $h->has($name)) {
                $h->set($name, $value);
            }
        };

        $set('X-Content-Type-Options', 'nosniff');
        $set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        if ($request->isSecure() && config('security.hsts')) {
            $set('Strict-Transport-Security', 'max-age=31536000'.(config('security.hsts_subdomains') ? '; includeSubDomains' : ''));
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $type = (string) $h->get('Content-Type');
        $guestMenu = $request->attributes->get('guest_menu') === true;

        if (str_contains($type, 'json')) {
            $set('Content-Security-Policy', Csp::api());
            $set('Cross-Origin-Resource-Policy', 'same-origin');
            $set('X-Frame-Options', 'DENY');
        } elseif (str_contains($type, 'text/html')) {
            $set('Cross-Origin-Opener-Policy', 'same-origin');
            $framedBySelf = strtoupper((string) $h->get('X-Frame-Options')) === 'SAMEORIGIN';
            if ($guestMenu) {
                // Scripts are limited by the <meta> policy inside the page; it may be embedded
                // on a restaurant's own site (the admin preview only by the admin itself).
                $set('Content-Security-Policy', "object-src 'none'; base-uri 'none'".($framedBySelf ? "; frame-ancestors 'self'" : ''));
            } else {
                $set('Content-Security-Policy', $framedBySelf ? str_replace("frame-ancestors 'none'", "frame-ancestors 'self'", Csp::html()) : Csp::html());
                $set('X-Frame-Options', 'DENY');
            }
        }

        return $response;
    }
}
