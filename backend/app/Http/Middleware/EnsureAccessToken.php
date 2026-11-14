<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile routes that act on "this device" need a real access token, not the
 * web app's cookie session.
 */
class EnsureAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->currentAccessToken() instanceof PersonalAccessToken) {
            return response()->json(['message' => __('mobile.token_required'), 'code' => 'mobile.token_required'], 400);
        }

        return $next($request);
    }
}
