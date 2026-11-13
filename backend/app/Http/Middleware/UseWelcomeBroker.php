<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The welcome e-mail for a new owner carries a set-password link that works
 * for 7 days (its own token table, OwnerWelcome::BROKER); ordinary
 * "forgot password" links keep their 60 minutes. The SPA sends welcome=1
 * when it opens such a link, and Fortify's reset then checks that broker.
 */
class UseWelcomeBroker
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/v1/auth/reset-password') && $request->boolean('welcome')) {
            config(['fortify.passwords' => 'welcome']);
        }

        return $next($request);
    }
}
