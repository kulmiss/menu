<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Security\SuperAdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two-factor step after a right password is one page for everyone. For
 * a Super Admin it keeps the Super Admin area's address rule (SUPER_ADMIN_IPS,
 * QUESTIONS #217): from another address it doesn't exist.
 */
class GuardSignInChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->hasSession() ? $request->session()->get('login.id') : null;
        if ($id !== null && ! SuperAdminAccess::ipAllowed($request) && User::whereKey($id)->value('is_super_admin')) {
            abort(404);
        }

        return $next($request);
    }
}
