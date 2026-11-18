<?php

namespace App\Http\Middleware;

use App\Support\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every Super Admin action is in the activity log (QUESTIONS #220): changes
 * and downloads that succeeded. Most screens already write their own, more
 * detailed entry; this adds `super.request` (method, route and its ids) for
 * any that didn't. Starting "Log in as" stays unrecorded (owner, QUESTIONS #170).
 */
class AuditSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->audited($request) || $response->getStatusCode() >= 400 || $request->attributes->get(ActivityLogger::LOGGED) === true) {
            return $response;
        }

        $route = $request->route();
        app(ActivityLogger::class)->log('super.request', null, [
            'method' => $request->method(),
            'route' => $route?->uri(),
            'ids' => array_filter($route?->parameters() ?? [], 'is_scalar'),
        ], null);

        return $response;
    }

    /** Changes, and reads that hand out data in bulk (CSV, PDF, backup files). */
    private function audited(Request $request): bool
    {
        if (str_contains((string) $request->route()?->uri(), '/impersonate')) {
            return false;
        }
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return true;
        }
        $uri = (string) $request->route()?->uri();

        return $request->query('format') === 'csv'
            || str_ends_with($uri, '.csv') || str_ends_with($uri, '/pdf') || str_ends_with($uri, '/file');
    }
}
