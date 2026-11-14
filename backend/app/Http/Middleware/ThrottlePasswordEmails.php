<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's forgot-password and reset-password routes have no limit of their
 * own; this adds the "password-email" limiter to just those two (SECURITY_REPORT.md M2).
 */
class ThrottlePasswordEmails
{
    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->is('api/v1/auth/forgot-password', 'api/v1/auth/reset-password')) {
            return $this->throttle->handle($request, $next, 'password-email');
        }

        return $next($request);
    }
}
