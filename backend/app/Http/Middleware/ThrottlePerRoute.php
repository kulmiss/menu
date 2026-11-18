<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * "throttle:N,M" with one counter per route (QA, 2026-11-17).
 *
 * Laravel's own key is only the person (or the address), so every route with a
 * plain limit shared one counter: 10 menu-address checks used up the sign-up
 * limit, 7 Super Admin searches blocked "resend welcome". Routes that share a
 * budget on purpose name it ("throttle:6,1,verify-mail"); named limiters
 * ("throttle:login", "throttle:super", …) build their own keys and are unchanged.
 */
class ThrottlePerRoute extends ThrottleRequests
{
    /**
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        if ($prefix === '' && is_numeric($maxAttempts) && ($route = $request->route())) {
            $prefix = sha1($route->methods()[0].'|'.$route->uri()).'|';

            return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
        }

        // Laravel spots a named limiter by the number of arguments: pass them on unchanged.
        return parent::handle(...func_get_args());
    }
}
