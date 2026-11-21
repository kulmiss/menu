<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The account pages ask for a new password once, with "Show password" instead
 * of a second box (QUESTIONS #222). Fortify's rules still want the
 * confirmation field, so it is the same value.
 */
class SinglePasswordField
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->has('password_confirmation')) {
            $request->merge(['password_confirmation' => (string) $request->input('password')]);
        }

        return $next($request);
    }
}
