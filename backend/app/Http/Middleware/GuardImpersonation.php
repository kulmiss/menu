<?php

namespace App\Http\Middleware;

use App\Support\ImpersonationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While "logged in as" a restaurant user (PLAN.md §5): ends the session after
 * 60 minutes, and refuses actions a Super Admin must never do on someone's
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
    ];

    public function __construct(private readonly ImpersonationManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has('impersonation_id')) {
            return $next($request);
        }

        if ($this->manager->expired($request)) {
            $this->manager->stop($request);

            return response()->json(['message' => __('platform.impersonation_expired'), 'code' => 'impersonation.expired'], 409);
        }

        $uri = ltrim(str_replace('api/', '', (string) $request->route()?->uri()), '/');
        if (! $request->isMethodSafe() && in_array($uri, self::BLOCKED, true)) {
            return response()->json(['message' => __('platform.impersonation_blocked'), 'code' => 'impersonation.blocked'], 403);
        }

        return $next($request);
    }
}
