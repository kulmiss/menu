<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SiteController;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\PublicMenu\MenuPageData;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Super Admin → Themes: a theme on a demo menu, in the same phone frame the
 * owner sees in Appearance (owner, 2026-11-21). Built in memory from the
 * demo's own menu; nothing is saved, no restaurant changes.
 */
class ThemePreviewController extends Controller
{
    /** The website café for left to right, the Arabic demo for right to left. */
    public const DEMOS = ['ltr' => SiteController::DEMO, 'rtl' => 'demo-mandi'];

    public function show(Request $request, MenuPageData $data): Response
    {
        $v = $request->validate([
            'style' => ['required', Rule::in(TenantAppearance::STYLES)],
            'primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => ['required', Rule::in(TenantAppearance::FONTS)],
            'scheme' => ['sometimes', Rule::in(['light', 'dark'])],
            'dir' => ['sometimes', Rule::in(['ltr', 'rtl'])],
            'photos' => ['sometimes', 'boolean'],
        ]);
        $dir = $v['dir'] ?? 'ltr';
        // Right to left exists only in the styles with "Menu direction" (H and M–T).
        if (! in_array($v['style'], TenantAppearance::DIRECTION_STYLES, true)) {
            $dir = 'ltr';
        }
        $headers = ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Frame-Options' => 'SAMEORIGIN', 'X-Robots-Tag' => 'noindex'];
        $tenant = Tenant::where('slug', self::DEMOS[$dir])->first();
        $request->attributes->set('guest_menu', true);
        if ($tenant === null) {
            return response(view('platform.theme-preview-missing')->render(), 200, $headers);
        }

        $appearance = app(CurrentTenant::class)->runAs($tenant, fn () => TenantAppearance::current());
        $appearance->fill([
            'style' => $v['style'],
            'primary_color' => strtolower($v['primary']),
            'font' => $v['font'],
            'menu_direction' => $dir,
            'show_photos' => $request->boolean('photos', true),
            'menu_layout' => null,
        ]);

        $html = view('public.menu', [...$data->build($tenant, $appearance, layouts: true, styles: TenantAppearance::STYLES), 'preview' => true])->render();
        if (isset($v['scheme'])) {
            // The dark-mode rules always on (dark) or never (light), as in the owner's preview.
            $html = (string) preg_replace('/@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/', $v['scheme'] === 'dark' ? '@media all' : '@media not all', $html);
        }

        return response($html, 200, $headers);
    }
}
