<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionState;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TenantSlugHistory;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\PublicMenu\MenuPageData;
use App\Support\Qr\QrMap;
use App\Tenancy\CurrentTenant;
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
    public function show(Request $request, MenuPageData $data, string $slug, ?string $branch = null): Response|RedirectResponse
    {
        $tenant = Tenant::where('slug', $slug)->first();
        $tail = ($branch === null ? '' : '/'.$branch).($request->getQueryString() ? '?'.$request->getQueryString() : '');

        if ($tenant === null) {
            $old = TenantSlugHistory::where('old_slug', $slug)->first();
            $current = $old ? Tenant::find($old->tenant_id) : null;
            abort_if($current === null, 404);

            return redirect('/'.$current->slug.$tail, 301);
        }

        $state = $tenant->subscriptionState();
        if (in_array($state, [SubscriptionState::Unavailable, SubscriptionState::Suspended], true)) {
            return response()->view('public.unavailable', ['tenant' => $tenant], 503, [
                'Retry-After' => '86400',
                'X-Robots-Tag' => 'noindex',
                'Cache-Control' => 'no-store',
            ]);
        }

        $place = null;
        if ($branch !== null) {
            $place = app(CurrentTenant::class)->runAs($tenant, fn () => Branch::where('slug', $branch)->where('is_active', true)->first());
            abort_if($place === null, 404);
            if ($place->is_default) {
                return redirect('/'.$tenant->slug.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
            }
        }

        // A server without the rewrite rules still gets the fast path.
        $cached = MenuPageCache::path($tenant->slug, $place?->slug);
        $html = MenuPageCache::enabled() && is_file($cached) ? (string) file_get_contents($cached) : null;
        if ($html === null) {
            $html = view('public.menu', $data->build($tenant, null, $place))->render();
            MenuPageCache::write($tenant->slug, $html, $place?->slug);
        }

        $response = response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
        ])->setEtag(md5($html));
        $response->isNotModified($request);

        return $response;
    }

    /**
     * /q/{code} when public/qr.php could not answer (no map file yet, or a
     * server without the rewrite rule): look it up and rebuild the map.
     */
    public function qr(string $code): RedirectResponse
    {
        $code = strtoupper($code);
        QrMap::rebuild();
        $map = QrMap::entries();
        abort_unless(isset($map[$code]), 404);

        return redirect($map[$code], 302)->header('Cache-Control', 'no-store');
    }
}
