<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionState;
use App\Models\Tenant;
use App\Models\TenantSlugHistory;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\PublicMenu\MenuPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The guest menu at /{slug} (PLAN.md §3.3, §15.2). Normally the web server
 * sends the static copy without reaching PHP; this renders it when the copy
 * is missing (first visit after a change) and stores it for the next guest.
 */
class PublicMenuController extends Controller
{
    public function show(Request $request, string $slug, MenuPageData $data): Response|RedirectResponse
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if ($tenant === null) {
            $old = TenantSlugHistory::where('old_slug', $slug)->first();
            $current = $old ? Tenant::find($old->tenant_id) : null;
            abort_if($current === null, 404);

            return redirect('/'.$current->slug.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
        }

        $state = $tenant->subscriptionState();
        if (in_array($state, [SubscriptionState::Unavailable, SubscriptionState::Suspended], true)) {
            return response()->view('public.unavailable', ['tenant' => $tenant], 503, [
                'Retry-After' => '86400',
                'X-Robots-Tag' => 'noindex',
                'Cache-Control' => 'no-store',
            ]);
        }

        // A server without the rewrite rules still gets the fast path.
        $cached = MenuPageCache::path($tenant->slug);
        $html = is_file($cached) ? (string) file_get_contents($cached) : null;
        if ($html === null) {
            $html = view('public.menu', $data->build($tenant))->render();
            MenuPageCache::write($tenant->slug, $html);
        }

        $response = response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
        ])->setEtag(md5($html));
        $response->isNotModified($request);

        return $response;
    }
}
