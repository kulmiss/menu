<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Support\MobileApp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super Admin → Mobile app, enforced on the server (QUESTIONS #193). Only for
 * requests from the app (it sends X-App-Version on every call); the web admin
 * never sends it. `mobile/config` always answers, so the app can explain.
 *
 *  - below the minimum version → 426 mobile.update_required
 *  - maintenance (Super Admins still get in) → 503 mobile.maintenance
 *  - app sign-in off for this kind of user → 403 mobile.app_off
 */
class MobileAppGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $version = (string) $request->header('X-App-Version', '');
        // Signing out (and dropping the push token) always works.
        $leaving = $request->is('api/v1/mobile/auth/logout*') || ($request->isMethod('DELETE') && $request->is('api/v1/mobile/push-tokens'));
        if ($version === '' || $leaving || $request->routeIs('mobile.config')) {
            return $next($request);
        }
        $m = MobileApp::settings();
        $web = url('/admin');

        if (MobileApp::older($version, (string) $m['min_version'])) {
            return response()->json(['message' => __('mobile.update_required'), 'code' => 'mobile.update_required', 'min_version' => $m['min_version'], 'web' => $web], 426);
        }

        // Signing in: the controller checks maintenance and sign-in once it knows who it is.
        if ($request->routeIs('mobile.login', 'mobile.two-factor')) {
            return $next($request);
        }
        $user = $request->user('sanctum');
        if ($m['maintenance'] && ! $user?->is_super_admin) {
            return response()->json(['message' => $m['maintenance_message'] ?: __('mobile.maintenance'), 'code' => 'mobile.maintenance', 'web' => $web], 503);
        }
        if ($user !== null && ! MobileApp::signInAllowed((bool) $user->is_super_admin)) {
            return response()->json(['message' => $m['sign_in_message'] ?: __('mobile.app_off'), 'code' => 'mobile.app_off', 'web' => $web], 403);
        }

        // Keep the device's app version up to date for Devices and Stats (one write per update).
        $token = $user?->currentAccessToken();
        if ($token instanceof PersonalAccessToken && $token->app_version !== mb_substr($version, 0, 20)) {
            $token->forceFill(['app_version' => mb_substr($version, 0, 20)])->saveQuietly();
        }

        return $next($request);
    }
}
