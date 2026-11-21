<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Api\Menu\RecordsChanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Models\Theme;
use App\Support\ActivityLogger;
use App\Support\Contrast;
use App\Support\Features;
use App\Support\MediaJanitor;
use App\Support\MyOrder;
use App\Support\PublicMenu\MenuPageData;
use App\Support\PublicMenu\PageTheme;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Appearance screen (PLAN.md §6 screen 9): settings, theme presets, contrast
 * warnings and the live preview of the guest page with unsaved settings.
 */
class AppearanceController extends Controller
{
    use RecordsChanges;

    public function show(): JsonResponse
    {
        Gate::authorize('restaurant.manage');

        return response()->json(['data' => $this->payload(TenantAppearance::current())]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate($this->rules());

        $model = TenantAppearance::first() ?? new TenantAppearance(TenantAppearance::DEFAULTS);
        $old = [$model->logo_media_id, $model->cover_media_id];
        $model->fill($data)->save();
        foreach ($old as $mediaId) {
            if (! in_array($mediaId, [$model->logo_media_id, $model->cover_media_id], true)) {
                MediaJanitor::releaseIfUnused($mediaId);
            }
        }
        $this->recordChange('appearance.updated', $model, ActivityLogger::diff($model));

        return response()->json(['data' => $this->payload($model->fresh(['logo', 'cover']) ?? $model)]);
    }

    /**
     * The guest page with the settings in the query string, not saved and not
     * cached. `scheme` (light|dark) shows it as on a phone in that mode, whatever
     * the computer's own mode (owner, 2026-11-21).
     */
    public function preview(Request $request, Tenant $tenant, MenuPageData $data): Response
    {
        Gate::authorize('restaurant.manage');
        $values = $request->validate($this->rules(partial: true));
        $scheme = $request->validate(['scheme' => ['sometimes', Rule::in(['light', 'dark'])]])['scheme'] ?? null;
        foreach (['show_prices', 'show_photos', 'show_descriptions', 'show_calories', ...TenantAppearance::EXTRA_FLAGS] as $flag) {
            if (array_key_exists($flag, $values)) {
                $values[$flag] = $request->boolean($flag);
            }
        }

        $appearance = TenantAppearance::current()->fill($values);
        $appearance->setRelation('logo', $appearance->logo_media_id ? Media::find($appearance->logo_media_id) : null);
        $appearance->setRelation('cover', $appearance->cover_media_id ? Media::find($appearance->cover_media_id) : null);

        $html = view('public.menu', [...$data->build($tenant, $appearance), 'preview' => true])->render();
        if ($scheme !== null) {
            // The dark-mode rules always on (dark) or never (light).
            $html = (string) preg_replace('/@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/', $scheme === 'dark' ? '@media all' : '@media not all', $html);
        }
        $request->attributes->set('guest_menu', true);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $ownMedia = Rule::exists('media', 'id')->where('tenant_id', app(CurrentTenant::class)->id());

        return [
            'primary_color' => [$req, 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => [$req, Rule::in(TenantAppearance::FONTS)],
            'style' => [$req, Rule::in($this->allowedStyles())],
            'banner_text' => ['sometimes', 'nullable', Rule::in(PageTheme::BANNER_TEXT)],
            'menu_direction' => ['sometimes', Rule::in(TenantAppearance::DIRECTIONS)],
            'show_prices' => [$req, 'boolean'],
            'show_photos' => [$req, 'boolean'],
            'menu_layout' => ['sometimes', 'nullable', Rule::in(TenantAppearance::LAYOUTS)],
            'show_descriptions' => [$req, 'boolean'],
            'show_calories' => [$req, 'boolean'],
            ...array_fill_keys(TenantAppearance::EXTRA_FLAGS, ['sometimes', 'boolean']),
            'logo_media_id' => ['sometimes', 'nullable', 'integer', $ownMedia],
            'cover_media_id' => ['sometimes', 'nullable', 'integer', $ownMedia],
            'theme_id' => ['sometimes', 'nullable', 'integer', Rule::exists('themes', 'id')->where('is_active', true)],
        ];
    }

    /** @return list<string> */
    private function allowedStyles(): array
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant ? Features::styles($tenant) : TenantAppearance::STYLES;
    }

    /** The plan has "Layouts": a layout other than the style's own (Plus and up). */
    private function layoutsAllowed(): bool
    {
        $tenant = app(CurrentTenant::class)->get();

        return $tenant === null || Features::enabled($tenant, 'layouts');
    }

    /** @return array<string, mixed> */
    private function payload(TenantAppearance $a): array
    {
        return [
            'primary_color' => $a->primary_color,
            'font' => $a->font,
            'style' => TenantAppearance::effectiveStyle($a->style, $this->allowedStyles()),
            'styles' => $this->allowedStyles(),
            'banner_text' => $a->banner_text,
            'menu_direction' => $a->menu_direction ?? 'auto',
            // Banner shape and where the name goes by default, per style (crop frame in the admin).
            'banner' => array_map(fn (array $s) => ['ratio' => $s['banner'], 'text' => $s['text']], PageTheme::STYLES),
            'show_prices' => $a->show_prices,
            'show_photos' => $a->show_photos,
            // Layouts (owner, 2026-11-21): the choice (null = the style's own), each style's own,
            // whether the plan allows another one, and what guests see now.
            'menu_layout' => $a->menu_layout,
            'style_layouts' => array_map(fn (array $s) => $s['layout'], PageTheme::STYLES),
            'layouts' => $this->layoutsAllowed(),
            'layout' => PageTheme::layout(TenantAppearance::effectiveStyle($a->style, $this->allowedStyles()), $a->menu_layout, $a->show_photos, $this->layoutsAllowed()),
            'show_descriptions' => $a->show_descriptions,
            'show_calories' => $a->show_calories,
            ...collect(TenantAppearance::EXTRA_FLAGS)->mapWithKeys(fn (string $f) => [$f => (bool) $a->{$f}])->all(),
            'theme_id' => $a->theme_id,
            // "My order" for the owner's switch: on for guests, and why not (QUESTIONS #236). New field; nothing else changed.
            'my_order' => MyOrder::state(app(CurrentTenant::class)->get() ?? throw new \LogicException('no restaurant'), $a),
            'logo' => $a->logo ? new MediaResource($a->logo) : null,
            'cover' => $a->cover ? new MediaResource($a->cover) : null,
            'warnings' => array_map(fn (string $key) => ['key' => $key, 'message' => __($key)], Contrast::warnings($a->primary_color)),
            'themes' => Theme::where('is_active', true)->orderBy('sort')->get()->map(fn (Theme $t) => [
                'id' => $t->id,
                'name' => $t->name['en'] ?? (string) (reset($t->name) ?: ''),
                ...$t->preset,
            ])->values()->all(),
        ];
    }
}
