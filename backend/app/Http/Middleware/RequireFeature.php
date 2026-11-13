<?php

namespace App\Http\Middleware;

use App\Support\Features;
use App\Support\UpgradeContact;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `feature:promotions` — 402 when the restaurant's plan doesn't include it. */
class RequireFeature
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $this->current->get();
        if ($tenant !== null && ! Features::enabled($tenant, $feature)) {
            return response()->json([
                'message' => __('platform.feature_off', ['feature' => __('platform.features.'.$feature)]),
                'code' => 'features.'.$feature,
                'upgrade' => UpgradeContact::payload(),
            ], 402);
        }

        return $next($request);
    }
}
