<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\BrandColor;
use App\Support\EmailLogo;
use App\Support\Features;
use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;
use App\Support\PublicMenu\MenuPageData;
use App\Support\Qr\QrImages;
use App\Support\SignUp\SignUp;
use App\Support\UpgradeContact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The platform's own public pages: the landing page (owner, 2026-11-13), the
 * Terms / Privacy pages the sign-up form links to, robots.txt and the
 * sitemap. Texts come from lang/en/landing.php and Super Admin → Settings;
 * plans and prices from the database. No invented numbers or reviews.
 */
class SiteController extends Controller
{
    /** Menu style → demo restaurant shown under "View live" (DemoSeeder). */
    public const STYLE_DEMOS = [
        'editorial' => 'demo-fine',
        'grid' => 'demo-pizza',
        'bistro' => 'demo-bakery',
        'minimal' => 'demo-nine-wells-coffee',
    ];

    public const DEMO = 'demo';

    public function home(): View
    {
        $s = PlatformSettings::all();
        $name = (string) $s['name'];
        $sections = array_map('boolval', [...PlatformSettings::DEFAULTS['landing_sections'], ...array_intersect_key((array) $s['landing_sections'], PlatformSettings::DEFAULTS['landing_sections'])]);
        $live = Tenant::whereIn('slug', [self::DEMO, ...array_values(self::STYLE_DEMOS)])->whereNull('suspended_at')->pluck('slug')->flip();
        $demoUrl = isset($live[self::DEMO]) ? url('/'.self::DEMO) : null;
        $contact = UpgradeContact::payload();
        $testimonials = array_values(array_filter((array) $s['landing_testimonials'], fn ($t) => is_array($t) && trim((string) ($t['quote'] ?? '')) !== '' && trim((string) ($t['name'] ?? '')) !== ''));
        $sections['testimonials'] = $sections['testimonials'] && $testimonials !== [];
        $plans = $this->plans($name, $contact);
        // The Free (sign-up) plan is promoted across the page (owner, 2026-11-13).
        $free = collect($plans)->first(fn (array $p) => $p['free'] && $p['highlight']);
        $sections['pricing'] = $sections['pricing'] && $plans !== [];
        $socials = array_filter((array) $s['socials'], fn ($u) => is_string($u) && preg_match('#^https?://#', $u) === 1);

        return view('site.landing', [
            'platform' => $name,
            'logo' => EmailLogo::url(),
            'colors' => BrandColor::palette($s['brand_color'] ?? null),
            'headline' => trim((string) $s['landing_headline']) ?: __('landing.hero.headline'),
            'subline' => trim((string) $s['landing_subline']) ?: __('landing.hero.subline'),
            'signupOpen' => (bool) $s['signup_open'],
            'sections' => $sections,
            'demoUrl' => $demoUrl,
            'qr' => $this->qrPath($demoUrl ?? url('/auth/sign-up')),
            'styles' => collect(self::STYLE_DEMOS)->map(fn ($slug) => isset($live[$slug]) ? url('/'.$slug) : null)->all(),
            'plans' => $sections['pricing'] ? $plans : [],
            'free' => $free,
            'contact' => $contact,
            'testimonials' => $testimonials,
            'socials' => $socials,
            'supportEmail' => $contact['email'],
            'jsonLd' => $this->jsonLd($name, $socials, $plans),
        ]);
    }

    public function legal(string $page): View
    {
        return view('site.legal', [
            'platform' => (string) PlatformSettings::get('name'),
            'title' => __("site.{$page}"),
            // Headings, lists and paragraphs as data; the view escapes every piece (no HTML from the setting).
            'doc' => LegalText::parse((string) PlatformSettings::get($page)),
        ]);
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /platform', 'Disallow: /auth', 'Disallow: /api', '', 'Sitemap: '.url('/sitemap.xml')];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = array_map(fn (string $path) => '<url><loc>'.e(url($path)).'</loc></url>', ['/', '/terms', '/privacy']);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.implode('', $urls).'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Public, active plans from the database: the sign-up plan (Free) first
     * and highlighted, then the paid plans. Other free plans (e.g. "Trial")
     * are left out: restaurants can't pick them.
     *
     * @param  array{whatsapp: string|null, email: string|null}  $contact
     * @return list<array<string, mixed>>
     */
    private function plans(string $platform, array $contact): array
    {
        $signup = SignUp::plan();
        $plans = Plan::where('is_public', true)->where('is_active', true)->orderBy('sort')->orderBy('price_minor')->get()
            ->filter(fn (Plan $p) => $p->price_minor > 0 || $p->id === $signup?->id)
            ->sortBy(fn (Plan $p) => $p->id === $signup?->id ? -1 : $p->sort);
        $features = DB::table('plan_features')->whereIn('plan_id', $plans->pluck('id'))->get()->groupBy('plan_id');

        return $plans->map(function (Plan $p) use ($signup, $features, $contact, $platform) {
            $own = ($features[$p->id] ?? collect())->pluck('enabled', 'feature_key')->map(fn ($v) => (bool) $v)->all();
            $on = array_map(fn ($default) => $default, Features::DEFAULTS);
            foreach ($on as $key => $default) {
                $on[$key] = $own[$key] ?? $default;
            }
            $limits = (array) ($p->limits ?? []);
            $limits['members'] ??= $limits['team'] ?? null;
            $lines = [];
            foreach (['items', 'photos', 'menus', 'branches', 'tables', 'members', 'languages'] as $key) {
                $n = $limits[$key] ?? null;
                if ($n !== null) {
                    $lines[] = trans_choice("landing.pricing.limit.{$key}", (int) $n, ['n' => number_format((int) $n)]);
                } elseif (in_array($key, ['items', 'photos'], true)) {
                    $lines[] = __("landing.pricing.unlimited.{$key}");
                }
            }
            $styles = count(array_filter(['editorial', 'grid', 'bistro', 'minimal'], fn ($st) => $on['style_'.$st]));
            $included = $styles > 0 ? [trans_choice('landing.pricing.feature.styles', $styles, ['n' => $styles])] : [];
            foreach (['pdf_menu', 'order_list', 'promotions', 'price_periods', 'insights', 'branches', 'import_export', 'remove_branding'] as $key) {
                if ($on[$key]) {
                    $included[] = __("landing.pricing.feature.{$key}", ['name' => $platform]);
                }
            }
            $isFree = $p->price_minor === 0;
            $name = $p->name[app()->getLocale()] ?? $p->name['en'] ?? $p->slug;
            $message = __('landing.pricing.whatsapp_text', ['plan' => $name]);

            return [
                'name' => $name,
                'free' => $isFree,
                'highlight' => $p->id === $signup?->id,
                'price' => $isFree ? __('landing.pricing.free') : MenuPageData::money($p->price_minor, $p->currency),
                'price_value' => $p->price_minor / 100,
                'currency' => $p->currency,
                'per' => $isFree ? __('landing.pricing.no_expiry') : __('landing.pricing.per.'.($p->interval === 'year' ? 'year' : 'month')),
                'limits' => $lines,
                'features' => $included,
                'contact' => $isFree ? null : ($contact['whatsapp'] !== null
                    ? ['href' => $contact['whatsapp'].'?text='.rawurlencode($message), 'label' => __('landing.pricing.whatsapp'), 'whatsapp' => true]
                    : ($contact['email'] !== null ? ['href' => 'mailto:'.$contact['email'].'?subject='.rawurlencode($message), 'label' => __('landing.pricing.email'), 'whatsapp' => false] : null)),
            ];
        })->values()->all();
    }

    /**
     * Dark modules of a QR code as one SVG path (drawn inline, no image request).
     *
     * @return array{size: int, path: string, url: string}
     */
    private function qrPath(string $url): array
    {
        $rows = QrImages::matrix($url);
        $d = '';
        foreach ($rows as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $d .= "M{$x} {$y}h1v1h-1z";
                }
            }
        }

        return ['size' => count($rows), 'path' => $d, 'url' => $url];
    }

    /**
     * @param  array<string, string>  $socials
     * @param  list<array<string, mixed>>  $plans
     */
    private function jsonLd(string $name, array $socials, array $plans): string
    {
        $org = array_filter([
            '@type' => 'Organization',
            'name' => $name,
            'url' => url('/'),
            'logo' => EmailLogo::url(),
            'sameAs' => array_values($socials) ?: null,
        ]);
        $app = [
            '@type' => 'SoftwareApplication',
            'name' => $name,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => url('/'),
            'description' => __('landing.meta_description'),
            'offers' => array_map(fn (array $p) => ['@type' => 'Offer', 'name' => $p['name'], 'price' => (string) $p['price_value'], 'priceCurrency' => $p['currency']], $plans) ?: null,
        ];

        return (string) json_encode(['@context' => 'https://schema.org', '@graph' => [$org, array_filter($app)]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
