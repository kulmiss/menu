<?php

namespace App\Http\Controllers;

use App\Support\ImpersonationManager;
use App\Support\Security\SuperAdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the built admin app (frontend/ → public/app) for its two areas
 * (QUESTIONS #215): /admin for restaurant teams and /{SUPER_ADMIN_PATH} for
 * Super Admins, so deep links and reloads work. Only the Super Admin area's
 * page names its own address (`<meta name="super-path">`).
 */
class SpaController extends Controller
{
    /** /admin/…: restaurant teams; a Super Admin goes to their own area (except "Log in as"). */
    public function restaurant(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect('/signin?'.http_build_query(['redirect' => $request->getRequestUri()]));
        }
        if ($user->is_super_admin && ! app(ImpersonationManager::class)->active($request)) {
            return redirect(SuperAdminAccess::url());
        }

        return $this->page();
    }

    /** /{SUPER_ADMIN_PATH}/…: Super Admins only; for anyone else signed in the area doesn't exist. */
    public function super(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect(SuperAdminAccess::url('sign-in').'?'.http_build_query(['redirect' => $request->getRequestUri()]));
        }
        // "Logged in as" a restaurant: that restaurant's admin until "End".
        if (app(ImpersonationManager::class)->active($request)) {
            return redirect('/admin');
        }
        abort_unless($user->is_super_admin, 404);

        return $this->page('<meta name="super-path" content="'.e(SuperAdminAccess::url()).'">');
    }

    /** /auth/…: the remaining signed-out pages of the app (sign-up, password reset, invitations). */
    public function auth(): Response
    {
        return $this->page();
    }

    private function page(string $head = ''): Response
    {
        $index = public_path('app/index.html');

        abort_unless(is_file($index), 503, __('menu.spa.not_built'));
        $html = (string) file_get_contents($index);
        if ($head !== '') {
            $html = preg_replace('#</head>#i', $head.'</head>', $html, 1) ?? $html;
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // The HTML must always be fresh; hashed assets under /app/assets are cached.
            'Cache-Control' => 'no-cache, must-revalidate, private',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
