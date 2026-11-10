<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the built admin SPA (frontend/ → public/app) for /admin, /platform
 * and /auth URLs, so deep links and page reloads work. The SPA's router
 * takes over from there.
 */
class SpaController extends Controller
{
    public function __invoke(): Response
    {
        $index = public_path('app/index.html');

        abort_unless(is_file($index), 503, __('menu.spa.not_built'));

        return response((string) file_get_contents($index), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // The HTML must always be fresh; hashed assets under /app/assets are cached.
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
