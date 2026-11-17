<?php

namespace App\Http\Middleware;

use App\Support\Security\SuperAdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Super Admin's web pages (/{SUPER_ADMIN_PATH}/…): a 404 from addresses
 * outside SUPER_ADMIN_IPS, as if the area didn't exist (QUESTIONS #217).
 */
class SuperAdminWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(SuperAdminAccess::ipAllowed($request), 404);

        return $next($request);
    }
}
