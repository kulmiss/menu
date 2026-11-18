<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Support\ImpersonationManager;
use App\Support\Security\SuperAdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The /api/v1/super area (QUESTIONS #215–#220): Super Admins only, from an
 * allowed address, with an app token scoped `super`, never while "logged in
 * as" a restaurant. Everyone else gets a 404, so the area is not revealed.
 * Two-factor sign-in must be set up first (403 until then).
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        abort_if(
            $user === null
            || ! $user->is_super_admin
            || app(ImpersonationManager::class)->active($request)
            || ($token instanceof PersonalAccessToken && ! $token->can('super'))
            || ! SuperAdminAccess::ipAllowed($request),
            404
        );

        if ($user->two_factor_confirmed_at === null) {
            return response()->json(['message' => __('platform.two_factor_required'), 'code' => 'platform.two_factor_required'], 403);
        }

        return $next($request);
    }
}
