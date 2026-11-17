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
        $signupOpen = (bool) $s['signup_open'];
        // Only quotes the Super Admin ticked as from a real customer (owner, 2026-11-17).
        $testimonials = array_values(array_filter((array) $s['landing_testimonials'], fn ($t) => is_array($t) && ($t['real'] ?? false) === true && trim((string) ($t['quote'] ?? '')) !== '' && trim((string) ($t['name'] ?? '')) !== ''));
        $sections['testimonials'] = $sections['testimonials'] && $testimonials !== [];
        $plans = $this->plans($name, $contact);
        // The Free (sign-up) plan is promoted across the page (owner, 2026-11-13).
        $free = collect($plans)->first(fn (array $p) => $p['free'] && $p['highlight']);
        $sections['pricing'] = $sections['pricing'] && $plans !== [];
        $sections['demo'] = $sections['demo'] && $demoUrl !== null;
        $socials = array_filter((array) $s['socials'], fn ($u) => is_string($u) && preg_match('#^https?://#', $u) === 1);
        $chat = $contact['whatsapp'] !== null ? $contact['whatsapp'].'?text='.rawurlencode(__('landing.chat.text', ['name' => $name])) : null;

        return view('site.landing', [
            'platform' => $name,
            'logo' => EmailLogo::url(),
            'colors' => BrandColor::palette($s['brand_color'] ?? null),
            'headline' => trim((string) $s['landing_headline']) ?: __($free ? 'landing.hero.headline' : 'landing.hero.headline_paid'),
            'subline' => trim((string) $s['landing_subline']) ?: __('landing.hero.subline'),
            'signupOpen' => $signupOpen,
            // The main button of every section: sign up, or (sign-up off) contact us.
            'cta' => $this->cta($signupOpen, $contact, $name),
            'sections' => $sections,
            'order' => $this->order((array) $s['landing_order'], $sections),
            'demoUrl' => $demoUrl,
            // The demo menu, else the sign-up page; none while both are unavailable.
            'qr' => $demoUrl !== null || $signupOpen ? $this->qrPath($demoUrl ?? url('/auth/sign-up')) : null,
            'styles' => collect(self::STYLE_DEMOS)->map(fn ($slug) => isset($live[$slug]) ? url('/'.$slug) : null)->filter()->all(),
            'plans' => $sections['pricing'] ? $plans : [],
            'free' => $free,
            'paidOnly' => $this->paidOnly($free),
            'contact' => $contact,
            'chat' => $chat,
            'testimonials' => $testimonials,
            'socials' => $socials,
            'supportEmail' => $contact['email'],
            'description' => __($free ? 'landing.meta_description' : 'landing.meta_description_paid'),
            'jsonLd' => $this->jsonLd($name, $socials, $plans, $sections['faq'], $free !== null),
        ]);
    }

    /**
     * @param  array{whatsapp: string|null, email: string|null}  $contact
     * @return array{href: string, label: string, external: bool, track: string|null}|null
     */
    private function cta(bool $signupOpen, array $contact, string $name): ?array
    {
        if ($signupOpen) {
            return ['href' => '/auth/sign-up', 'label' => __('landing.create_account'), 'external' => false, 'track' => 'signup'];
        }
        $text = __('landing.contact_text', ['name' => $name]);
        if ($contact['whatsapp'] !== null) {
            return ['href' => $contact['whatsapp'].'?text='.rawurlencode($text), 'label' => __('landing.contact_whatsapp'), 'external' => true, 'track' => null];
        }
        if ($contact['email'] !== null) {
            return ['href' => 'mailto:'.$contact['email'].'?subject='.rawurlencode($text), 'label' => __('landing.contact_email'), 'external' => false, 'track' => null];
        }

        return null;
    }

    /**
     * Sections in the Super Admin's order; unknown keys dropped, missing ones
     * added at the end. Only sections that are switched on.
     *
     * @param  array<int, mixed>  $saved
     * @param  array<string, bool>  $on
     * @return list<string>
     */
    private function order(array $saved, array $on): array
    {
        $known = PlatformSettings::DEFAULTS['landing_order'];
        $order = array_values(array_unique(array_filter($saved, fn ($k) => is_string($k) && in_array($k, $known, true))));
        $order = [...$order, ...array_diff($known, $order)];

        return array_values(array_filter($order, fn (string $k) => $on[$k] ?? false));
    }

    /**
     * "What you get" cards whose feature the Free plan does not include get a
     * "Paid plans" tag, so the page never promises more than Free gives.
     *
     * @param  array<string, mixed>|null  $free
     * @return array<string, bool>
     */
    private function paidOnly(?array $free): array
    {
        $on = $free['on'] ?? null;
        if ($on === null) {
            return [];
        }

        return [
            'styles' => ($free['style_count'] ?? 0) < 2,
            'promotions' => ! ($on['promotions'] ?? false) && ! ($on['price_periods'] ?? false),
            'pdf' => ! ($on['pdf_menu'] ?? false),
            'insights' => ! ($on['insights'] ?? false),
        ];
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

        $previous = null;
        $out = [];
        foreach ($plans as $p) {
            $own = ($features[$p->id] ?? collect())->pluck('enabled', 'feature_key')->map(fn ($v) => (bool) $v)->all();
            $on = [];
            foreach (Features::DEFAULTS as $key => $default) {
                $on[$key] = $own[$key] ?? $default;
            }
            $limits = (array) ($p->limits ?? []);
            $lines = [];
            // Dishes and menus first: unlimited on every plan of the ladder (QUESTIONS #184).
            $lines[] = ($limits['items'] ?? null) === null && ($limits['menus'] ?? null) === null
                ? __('landing.pricing.unlimited.items')
                : trans_choice('landing.pricing.limit.items', (int) ($limits['items'] ?? 0), ['n' => number_format((int) ($limits['items'] ?? 0))]);
            foreach (['photos', 'branches'] as $key) {
                $n = $limits[$key] ?? null;
                $lines[] = $n === null ? __("landing.pricing.unlimited.{$key}") : trans_choice("landing.pricing.limit.{$key}", (int) $n, ['n' => number_format((int) $n)]);
            }
            $styles = count(array_filter(['editorial', 'grid', 'bistro', 'minimal'], fn ($st) => $on['style_'.$st]));
            $have = [];
            if ($styles > 0) {
                $have['styles:'.$styles] = trans_choice('landing.pricing.feature.styles', $styles, ['n' => $styles]);
            }
            foreach (['pdf_menu', 'order_list', 'print', 'import_export', 'promotions', 'insights', 'price_periods', 'remove_branding'] as $key) {
                if ($key === 'print' || $on[$key]) {
                    $have[$key] = __("landing.pricing.feature.{$key}", ['name' => $platform]);
                }
            }
            // A ladder: each plan lists only what it adds to the one before.
            $included = $previous === null ? $have : array_diff_key($have, $previous['have']);
            $isFree = $p->price_minor === 0;
            $name = $p->name[app()->getLocale()] ?? $p->name['en'] ?? $p->slug;
            $message = __('landing.pricing.whatsapp_text', ['plan' => $name]);
            $year = $isFree || $p->interval !== 'month' || ! $p->price_year_minor ? null : __('landing.pricing.yearly', [
                'price' => MenuPageData::money((int) $p->price_year_minor, $p->currency),
                'months' => max(0, (int) round(12 - $p->price_year_minor / max(1, $p->price_minor))),
            ]);

            $out[] = [
                'name' => $name,
                'free' => $isFree,
                'highlight' => $p->id === $signup?->id,
                'price' => $isFree ? __('landing.pricing.free') : MenuPageData::money($p->price_minor, $p->currency),
                'price_value' => $p->price_minor / 100,
                'currency' => $p->currency,
                'per' => $isFree ? __('landing.pricing.no_expiry') : __('landing.pricing.per.'.($p->interval === 'year' ? 'year' : 'month')),
                'year' => $year,
                'on' => $on,
                'style_count' => $styles,
                'everything' => $previous === null ? null : __('landing.pricing.everything', ['plan' => $previous['name']]),
                'limits' => $previous === null ? $lines : array_values(array_diff($lines, $previous['lines'])),
                'features' => array_values($included),
                'contact' => $isFree ? null : ($contact['whatsapp'] !== null
                    ? ['href' => $contact['whatsapp'].'?text='.rawurlencode($message), 'label' => __('landing.pricing.whatsapp'), 'whatsapp' => true]
                    : ($contact['email'] !== null ? ['href' => 'mailto:'.$contact['email'].'?subject='.rawurlencode($message), 'label' => __('landing.pricing.email'), 'whatsapp' => false] : null)),
            ];
            $previous = ['name' => $name, 'have' => $have, 'lines' => $lines];
        }

        return $out;
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
    private function jsonLd(string $name, array $socials, array $plans, bool $faq, bool $free): string
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
            'description' => __($free ? 'landing.meta_description' : 'landing.meta_description_paid'),
            'offers' => array_map(fn (array $p) => ['@type' => 'Offer', 'name' => $p['name'], 'price' => (string) $p['price_value'], 'priceCurrency' => $p['currency']], $plans) ?: null,
        ];

        $graph = [$org, array_filter($app)];
        if ($faq) {
            $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_values(array_map(fn (array $i) => [
                '@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']],
            ], (array) __('landing.faq.items')))];
        }

        return (string) json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
