<?php

namespace App\Http\Middleware;

use App\Support\ImpersonationManager;
use App\Support\Security\OwnerTwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the Super Admin requires two-factor for Owners (off by default), an
 * Owner without it can only reach the setup screens (/me and Fortify), not the
 * restaurant. "Log in as" is not affected.
 */
class EnsureOwnerTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && ! app(ImpersonationManager::class)->active($request) && OwnerTwoFactor::required($user)) {
            return response()->json(['message' => __('security.owner_two_factor_required'), 'code' => 'two_factor.required'], 403);
        }

        return $next($request);
    }
}
