<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Support\ImpersonationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The /api/v1/restaurant/{tenant} area (QUESTIONS #215, #220): restaurant
 * teams only. A Super Admin gets here only through "Log in as"; an app token
 * must be scoped `restaurant`. Others get a 404 ('tenant' then limits the
 * data to a restaurant the person belongs to).
 */
class EnsureRestaurantArea
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        abort_if(
            $user === null
            || ($user->is_super_admin && ! app(ImpersonationManager::class)->active($request))
            || ($token instanceof PersonalAccessToken && ! $token->can('restaurant')),
            404
        );

        return $next($request);
    }
}
