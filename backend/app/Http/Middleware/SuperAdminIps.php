<?php

namespace App\Http\Middleware;

use App\Support\Security\SuperAdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Super Admin API from an address outside SUPER_ADMIN_IPS: 404 before the
 * sign-in check, so a signed-out visitor gets no "401 sign in first" that would
 * show the area exists (QUESTIONS #217). Runs before Authenticate (bootstrap/app.php).
 */
class SuperAdminIps
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(SuperAdminAccess::ipAllowed($request), 404);

        return $next($request);
    }
}
