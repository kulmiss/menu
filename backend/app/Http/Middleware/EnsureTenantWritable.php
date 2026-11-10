<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After the grace period an expired restaurant becomes read-only: reads still
 * work, changes get HTTP 402 with a translated message.
 */
class EnsureTenantWritable
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->current->get();

        if ($tenant !== null && ! $request->isMethodSafe() && ! $tenant->subscriptionState()->allowsWrites()) {
            return response()->json([
                'message' => __('menu.subscription.read_only'),
                'code' => 'subscription.read_only',
            ], 402);
        }

        return $next($request);
    }
}
