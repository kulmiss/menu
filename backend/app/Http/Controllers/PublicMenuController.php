<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionState;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\TenantSlugHistory;
use App\Support\Features;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\PublicMenu\MenuPageData;
use App\Support\PublicMenu\PdfMenu;
use App\Support\Qr\QrMap;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

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

    /**
     * The printable menu for guests, when the restaurant shows the link:
     * built once and kept next to the static page, so it is removed (and
     * rebuilt on the next request) whenever the menu changes.
     */
    public function pdf(Request $request, MenuPageData $data, string $slug, ?string $branch = null): Response
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        abort_unless($tenant->subscriptionState()->showsPublicMenu(), 404);
        $current = app(CurrentTenant::class);
        [$place, $appearance] = $current->runAs($tenant, fn () => [
            $branch === null ? null : Branch::where('slug', $branch)->where('is_active', true)->firstOrFail(),
            TenantAppearance::current(),
        ]);
        abort_unless($appearance->show_pdf_link && Features::for($tenant)['pdf_menu'], 404);

        $file = MenuPageCache::dir($tenant->slug, $place?->slug).'/menu.pdf';
        if (! is_file($file)) {
            $pdf = $current->runAs($tenant, fn () => (new PdfMenu)->render($data->build($tenant, null, $place), [
                'photos' => (bool) $appearance->show_photos,
                'columns' => 1,
            ]));
            File::ensureDirectoryExists(dirname($file));
            file_put_contents($file.'.tmp', $pdf);
            rename($file.'.tmp', $file);
        }
        $body = (string) file_get_contents($file);

        $response = response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$tenant->slug.'-menu.pdf"',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
        ])->setEtag(md5($body));
        $response->isNotModified($request);

        return $response;
    }
}
