<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the restaurant from the `{tenant}` route parameter, but only when the
 * signed-in user is a member of it. Anyone else gets a 404, so other
 * restaurants cannot even be detected.
 */
class ResolveTenant
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $param = $request->route('tenant');
        $user = $request->user();

        abort_if($user === null, 404);

        $tenant = match (true) {
            $param instanceof Tenant => $param,
            is_numeric($param) => Tenant::find((int) $param),
            default => null,
        };
        $membership = $tenant ? $user->membershipFor($tenant) : null;

        abort_if($tenant === null || $membership === null, 404);

        $this->current->set($tenant, $membership->setRelation('tenant', $tenant));

        // Controllers receive the Tenant model, not the raw id.
        $request->route()?->setParameter('tenant', $tenant);

        return $next($request);
    }
}
