<?php

namespace App\Http\Middleware;

use App\Support\ImpersonationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While "logged in as" a restaurant user (PLAN.md §5): ends the session after
 * 2 hours, and refuses actions a Super Admin must never do on someone's
 * behalf — password, e-mail and two-factor changes, team ownership, the
 * restaurant address and deleting the restaurant.
 */
class GuardImpersonation
{
    /** Route URIs (without the api/ prefix) that stay blocked. */
    private const BLOCKED = [
        'v1/auth/user/password',
        'v1/auth/user/profile-information',
        'v1/auth/user/two-factor-authentication',
        'v1/auth/user/confirmed-two-factor-authentication',
        'v1/auth/user/two-factor-recovery-codes',
        'v1/t/{tenant}/account/slug',
        'v1/t/{tenant}/members/{member}',
        'v1/t/{tenant}/account',
        'v1/me/sessions/logout-others',
        // The app's own account routes (QUESTIONS #188).
        'v1/mobile/account/profile',
        'v1/mobile/account/password',
        'v1/mobile/account/two-factor',
        'v1/mobile/account/two-factor/confirm',
        'v1/mobile/account/two-factor/recovery-codes',
        'v1/mobile/auth/logout-all',
        'v1/mobile/devices/{device}',
        'v1/mobile/push-tokens',
    ];

    public function __construct(private readonly ImpersonationManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        // The app's "log in as" token (QUESTIONS #188) ends by itself after 2 hours (expires_at).
        if ($this->manager->token($request) !== null) {
            return $this->blocked($request) ?? $next($request);
        }

        if (! $request->hasSession() || ! $request->session()->has('impersonation_id')) {
            return $next($request);
        }

        if ($this->manager->expired($request)) {
            $this->manager->stop($request);

            return response()->json(['message' => __('platform.impersonation_expired'), 'code' => 'impersonation.expired'], 409);
        }

        return $this->blocked($request) ?? $next($request);
    }

    private function blocked(Request $request): ?Response
    {
        $uri = ltrim(str_replace('api/', '', (string) $request->route()?->uri()), '/');
        if (! $request->isMethodSafe() && in_array($uri, self::BLOCKED, true)) {
            return response()->json(['message' => __('platform.impersonation_blocked'), 'code' => 'impersonation.blocked'], 403);
        }

        return null;
    }
}
