<?php

namespace App\Http\Middleware;

use App\Support\ImpersonationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The /api/v1/platform area: Super Admins only, and only with two-factor
 * sign-in set up (PLAN.md §5). Never while "logged in as" a restaurant.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_if($user === null || ! $user->is_super_admin || app(ImpersonationManager::class)->active($request), 404);

        if ($user->two_factor_confirmed_at === null) {
            return response()->json(['message' => __('platform.two_factor_required'), 'code' => 'platform.two_factor_required'], 403);
        }

        return $next($request);
    }
}
