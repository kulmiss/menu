<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Auth\GoogleLogin;
use App\Support\BrandColor;
use App\Support\EmailLogo;
use App\Support\Features;
use App\Support\Help\HelpLibrary;
use App\Support\Help\HelpRenderer;
use App\Support\Landing\Landing2;
use App\Support\Landing\Landing2Texts;
use App\Support\Landing\LandingImages;
use App\Support\Landing\LandingTexts;
use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;
use App\Support\PublicMenu\MenuPageData;
use App\Support\Qr\QrImages;
use App\Support\SignUp\SignUp;
use App\Support\Site\Footer;
use App\Support\UpgradeContact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
        'mesob' => 'demo-mesob',
        'lounge' => 'demo-lounge',
        'board' => 'demo-street',
        'arabic' => 'demo-mandi',
        'islamic' => 'demo-halal',
        'somali' => 'demo-xamar',
        'mono' => 'demo-atelier',
        'colour' => 'demo-juicy',
        'coffee' => 'demo-jebena',
        'grill' => 'demo-firewood',
        'garden' => 'demo-green-spoon',
        'coastal' => 'demo-dhow',
        'luxe' => 'demo-maison',
        'burger' => 'demo-bigbite',
        'injera' => 'demo-shekla',
        'juice' => 'demo-spris',
    ];

    public const DEMO = 'demo';

    /**
     * The demo menus the website links to: on a tablet or computer they get a bar to
     * see them in a phone frame or as they are (owner, 2026-11-19).
     *
     * @return list<string>
     */
    public static function demoSlugs(): array
    {
        return [self::DEMO, ...array_values(self::STYLE_DEMOS)];
    }

    /** The home page designs the Super Admin can pick (owner, 2026-11-22). */
    public const HOME_DESIGNS = ['landing1', 'landing2'];

    /** The home page: Landing 1, or Landing 2 once the Super Admin picks it. */
    public function home(): View|SymfonyResponse
    {
        if (PlatformSettings::get('home_design') === 'landing2') {
            return $this->landing2Page('en', true);
        }

        return view('site.landing', $this->landing(LandingTexts::resolver()));
    }

    /**
     * The Amharic home page (Landing 2 only, owner 2026-11-22). While Landing 1 is the home
     * page there is no Amharic home page: back to "/". A restaurant that had the address
     * "am" before it was reserved keeps it.
     */
    public function homeAm(): View|SymfonyResponse
    {
        if (Tenant::where('slug', 'am')->exists()) {
            return app()->call([app(PublicMenuController::class), 'show'], ['slug' => 'am']);
        }
        if (PlatformSettings::get('home_design') !== 'landing2') {
            return redirect('/');
        }

        return $this->landing2Page('am', true);
    }

    /** Landing 2 at /landing2, to look at before it is the home page (always noindex). */
    public function landing2(): View|SymfonyResponse
    {
        return $this->landing2Page('en', false);
    }

    /** Landing 2 in Amharic at /am/landing2 (always noindex). */
    public function landing2Am(): View|SymfonyResponse
    {
        if (Tenant::where('slug', 'am')->exists()) {
            return app()->call([app(PublicMenuController::class), 'show'], ['slug' => 'am', 'branch' => 'landing2']);
        }

        return $this->landing2Page('am', false);
    }

    /**
     * Landing 2 in English or Amharic only (owner, 2026-11-22). The language is set for this
     * page only, then put back.
     */
    private function landing2Page(string $locale, bool $isHome): SymfonyResponse
    {
        $before = app()->getLocale();
        app()->setLocale($locale);
        try {
            if ($locale === 'am') {
                Landing2Texts::applyLines();
            }
            $t = Landing2Texts::resolver($locale);

            return response(view('site.landing2', [...$this->landing($t, $locale), ...$this->landing2Extras($locale, $isHome, $t), 'isHome' => $isHome])->render());
        } finally {
            app()->setLocale($before);
        }
    }

    /**
     * Landing 2's own parts (owner, 2026-11-22): every menu style A–T, the switcher, the
     * switches, the numbers, restaurants, payment methods, video, poster and languages.
     *
     * @param  \Closure(string, array<string, string|int>=): mixed  $t
     * @return array<string, mixed>
     */
    private function landing2Extras(string $locale, bool $isHome, \Closure $t): array
    {
        $live = Tenant::whereIn('slug', array_values(self::STYLE_DEMOS))->whereNull('suspended_at')->pluck('slug')->flip();
        $on = Landing2::sections();
        // The hero phone shows the demo café; its chip is the café's own style.
        $demoStyle = DB::table('tenant_appearance')->join('tenants', 'tenants.id', '=', 'tenant_appearance.tenant_id')
            ->where('tenants.slug', self::DEMO)->value('tenant_appearance.style');
        $styles = collect(TenantAppearance::STYLES)->map(fn (string $style) => [
            'style' => $style,
            'name' => (string) $t('style_names.'.$style),
            'href' => isset($live[self::STYLE_DEMOS[$style]]) ? url('/'.self::STYLE_DEMOS[$style]) : null,
            'shot' => LandingImages::style($style),
            'switch' => LandingImages::switchShot($style),
        ])->all();
        $numbers = $on['numbers'] ? Landing2::numbers($locale) : [];
        $restaurants = $on['restaurants'] ? Landing2::restaurants($locale) : [];
        $payments = array_values(array_filter(Landing2::payments(), fn (array $p) => $p['on']));
        $video = $on['video'] ? Landing2::video() : null;
        $paths = $isHome ? ['en' => '/', 'am' => '/am'] : ['en' => '/landing2', 'am' => '/am/landing2'];
        $other = $locale === 'en' ? 'am' : 'en';

        return [
            'allStyles' => $styles,
            'demoStyle' => in_array($demoStyle, TenantAppearance::STYLES, true) ? $demoStyle : null,
            'on' => [...$on,
                'numbers' => $numbers !== [],
                'restaurants' => $restaurants !== [],
                'payments' => $on['payments'] && $payments !== [],
                'video' => $video !== null,
            ],
            'numbers' => $numbers,
            'restaurants' => $restaurants,
            'payments' => array_map(fn (array $p) => ['label' => $locale === 'am' && $p['label_am'] !== '' ? $p['label_am'] : $p['label_en'], 'icon' => $p['icon']], $payments),
            'video' => $video,
            'locale' => $locale,
            'urls' => ['en' => url($paths['en']), 'am' => url($paths['am'])],
            'otherLocale' => $other,
            'canonical' => url($paths[$locale]),
            // The poster's line in both languages: the poster's language can differ from the page's.
            'posterScan' => ['en' => (string) Landing2Texts::resolver('en')('poster.scan'), 'am' => (string) Landing2Texts::resolver('am')('poster.scan')],
            'heroPhoto' => $on['hero_photo'] && is_file(public_path('landing/hero-photo.webp'))
                ? 'data:image/webp;base64,'.base64_encode((string) file_get_contents(public_path('landing/hero-photo.webp'))) : null,
            'footer' => Landing2::footer($locale),
        ];
    }

    /**
     * Everything both home page designs show, from live data. $t: the page's texts (Landing 1:
     * LandingTexts; Landing 2: Landing2Texts in English or Amharic).
     *
     * @param  \Closure(string, array<string, string|int>=): mixed  $t
     * @return array<string, mixed>
     */
    private function landing(\Closure $t, string $locale = 'en'): array
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
        $plans = $this->plans($name, $contact, $locale === 'am' ? Landing2Texts::all('am') : null);
        // The Free (sign-up) plan is promoted across the page (owner, 2026-11-13).
        $free = collect($plans)->first(fn (array $p) => $p['free'] && $p['highlight']);
        $sections['pricing'] = $sections['pricing'] && $plans !== [];
        $sections['demo'] = $sections['demo'] && $demoUrl !== null;
        // Social profiles for search engines (JSON-LD sameAs): the ones in the live footer (#230).
        $socials = array_filter(Footer::published()['socials']);
        $chat = $contact['whatsapp'] !== null ? $contact['whatsapp'].'?text='.rawurlencode($t('chat.text', ['name' => $name])) : null;

        return [
            'platform' => $name,
            'logo' => EmailLogo::url(),
            'colors' => BrandColor::palette($s['brand_color'] ?? null),
            't' => $t,
            'headline' => $t($free ? 'hero.headline' : 'hero.headline_paid'),
            'subline' => $t('hero.subline'),
            'signupOpen' => $signupOpen,
            // "Continue with Google" next to the main button, while sign-up is open (owner, 2026-11-17).
            'google' => $signupOpen && GoogleLogin::enabled(),
            // The main button of every section: sign up, or (sign-up off) contact us.
            'cta' => $this->cta($signupOpen, $contact, $name, $t),
            'sections' => $sections,
            'order' => $this->order((array) $s['landing_order'], $sections),
            'demoUrl' => $demoUrl,
            // The demo menu, else the sign-up page; none while both are unavailable.
            'qr' => $demoUrl !== null || $signupOpen ? $this->qrPath($demoUrl ?? url('/signup')) : null,
            // "Menu styles" under the demo: each style with a live demo, with its phone screenshot when there is one.
            'styles' => collect(self::STYLE_DEMOS)->filter(fn ($slug) => isset($live[$slug]))
                ->map(fn ($slug, $style) => ['href' => url('/'.$slug), 'shot' => LandingImages::style($style)])->all(),
            'plans' => $sections['pricing'] ? $plans : [],
            'free' => $free,
            'paidOnly' => $this->paidOnly($free),
            'contact' => $contact,
            'chat' => $chat,
            'testimonials' => $testimonials,
            'supportEmail' => $contact['email'],
            'description' => $t($free ? 'meta_description' : 'meta_description_paid'),
            'shots' => array_combine(['hero', 'hero_dark', 'demo', 'demo_dark'], array_map(LandingImages::phone(...), ['hero', 'hero_dark', 'demo', 'demo_dark'])),
            'ogImage' => LandingImages::og()['url'],
            'jsonLd' => $this->jsonLd($name, $socials, $plans, $sections['faq'], $free !== null, $t),
        ];
    }

    /**
     * The one-page sales sheet for owners (Super Admin → Landing page, QUESTIONS #234):
     * A4, live plans and prices, the demo QR code and the contact details. Print it,
     * or "Save as PDF" in the browser.
     */
    public function salesSheet(): View
    {
        $s = PlatformSettings::all();
        $name = (string) $s['name'];
        $contact = UpgradeContact::payload();
        $demo = Tenant::where('slug', self::DEMO)->whereNull('suspended_at')->exists() ? url('/'.self::DEMO) : url('/');

        return view('site.sales-sheet', [
            'platform' => $name,
            'logo' => EmailLogo::url(),
            'colors' => BrandColor::palette($s['brand_color'] ?? null),
            'plans' => $this->plans($name, $contact),
            'qr' => $this->qrPath($demo),
            'site' => preg_replace('#^https?://#', '', rtrim(url('/'), '/')),
            'contact' => $contact,
            'styles' => count(TenantAppearance::STYLES),
        ]);
    }

    /**
     * @param  array{whatsapp: string|null, email: string|null}  $contact
     * @param  \Closure(string, array<string, string>=): string  $t
     * @return array{href: string, label: string, external: bool, track: string|null}|null
     */
    private function cta(bool $signupOpen, array $contact, string $name, \Closure $t): ?array
    {
        if ($signupOpen) {
            return ['href' => '/signup', 'label' => $t('create_account'), 'external' => false, 'track' => 'signup'];
        }
        $text = $t('contact_text', ['name' => $name]);
        if ($contact['whatsapp'] !== null) {
            return ['href' => $contact['whatsapp'].'?text='.rawurlencode($text), 'label' => $t('contact_whatsapp'), 'external' => true, 'track' => null];
        }
        if ($contact['email'] !== null) {
            return ['href' => 'mailto:'.$contact['email'].'?subject='.rawurlencode($text), 'label' => $t('contact_email'), 'external' => false, 'track' => null];
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

    /** Pages linked from the footer that show their default text until the Super Admin writes one (#230). */
    private const SITE_PAGES = ['about', 'contact', 'cookies'];

    public function legal(string $page): View|SymfonyResponse
    {
        // A restaurant that took this address before it was reserved keeps it (its menu is shown).
        if (in_array($page, self::SITE_PAGES, true) && Tenant::where('slug', $page)->exists()) {
            return app()->call([app(PublicMenuController::class), 'show'], ['slug' => $page]);
        }
        $text = (string) PlatformSettings::get($page);
        if ($text === '' && in_array($page, self::SITE_PAGES, true)) {
            $text = LegalText::default($page);
        }

        return view('site.legal', [
            'page' => $page,
            'platform' => (string) PlatformSettings::get('name'),
            'title' => __("site.{$page}"),
            // Headings, lists and paragraphs as data; the view escapes every piece (no HTML from the setting).
            'doc' => LegalText::parse($text),
            // Short pages: every section open.
            'open' => in_array($page, self::SITE_PAGES, true),
            // Contact us: the footer's contact details under the text.
            'contactCard' => $page === 'contact' ? Footer::view(Footer::published())['contact'] : null,
            'canonical' => url('/'.$page),
        ]);
    }

    /** Help centre for everyone (#230): the help articles marked `public`, grouped by topic. */
    public function help(): View
    {
        $articles = HelpLibrary::visible('restaurant', null, null, publicOnly: true);
        $topics = (array) __('help.topics.restaurant');
        $groups = [];
        foreach ($articles as $a) {
            $groups[$a['topic']][] = ['slug' => $a['slug'], 'title' => $a['title'], 'summary' => trim(strip_tags((new HelpRenderer)->html((new HelpRenderer)->markdown((string) $a['summary']))))];
        }
        uksort($groups, fn ($x, $y) => array_search($x, array_keys($topics), true) <=> array_search($y, array_keys($topics), true));

        return view('site.help', [
            'platform' => (string) PlatformSettings::get('name'),
            'groups' => array_map(fn ($items, $topic) => ['title' => $topics[$topic] ?? Str::headline($topic), 'items' => $items], $groups, array_keys($groups)),
        ]);
    }

    public function helpArticle(string $slug): View
    {
        $public = HelpLibrary::visible('restaurant', null, null, publicOnly: true);
        $article = collect($public)->firstWhere('slug', $slug);
        abort_if($article === null, 404);
        $render = new HelpRenderer;
        $html = $render->html($render->markdown((string) $article['body']));
        // Links to other help articles: to their public page, or plain text when that one is only in the admin.
        $slugs = array_column($public, 'slug');
        $html = (string) preg_replace_callback('#<a data-help href="help:([a-z0-9\-]+)">(.*?)</a>#s', fn ($m) => in_array($m[1], $slugs, true)
            ? '<a href="/support/'.$m[1].'">'.$m[2].'</a>'
            : '<span>'.$m[2].'</span>', $html);

        return view('site.help-article', [
            'platform' => (string) PlatformSettings::get('name'),
            'article' => ['title' => $article['title'], 'summary' => trim(strip_tags($render->html($render->markdown((string) $article['summary'])))), 'html' => $html, 'slug' => $slug],
        ]);
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Disallow: /admin', 'Disallow: /auth', 'Disallow: /api', '', 'Sitemap: '.url('/sitemap.xml')];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $help = array_map(fn (array $a) => '/support/'.$a['slug'], array_values(HelpLibrary::visible('restaurant', null, null, publicOnly: true)));
        $urls = array_map(fn (string $path) => '<url><loc>'.e(url($path)).'</loc></url>', ['/', '/terms', '/privacy', '/about', '/contact', '/cookies', '/support', ...$help]);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.implode('', $urls).'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Public, active plans from the database: the sign-up plan (Free) first
     * and highlighted, then the paid plans. Other free plans (e.g. "Trial")
     * are left out: restaurants can't pick them.
     *
     * @param  array{whatsapp: string|null, email: string|null}  $contact
     * @param  array<string, string>|null  $amTexts  Landing 2 in Amharic: names for plans without an Amharic name
     * @return list<array<string, mixed>>
     */
    private function plans(string $platform, array $contact, ?array $amTexts = null): array
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
            $styles = count(array_filter(TenantAppearance::STYLES, fn ($st) => $on['style_'.$st]));
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
            $name = $p->name[app()->getLocale()] ?? $amTexts["pricing.plan_names.{$p->slug}"] ?? $p->name['en'] ?? $p->slug;
            $message = __('landing.pricing.whatsapp_text', ['plan' => $name]);
            $year = $isFree || $p->interval !== 'month' || ! $p->price_year_minor ? null : __('landing.pricing.yearly', [
                'price' => MenuPageData::money((int) $p->price_year_minor, $p->currency),
                'months' => max(0, (int) round(12 - $p->price_year_minor / max(1, $p->price_minor))),
            ]);

            // Landing 2's Monthly / Yearly switch (owner, 2026-11-22): the yearly price and months saved.
            $yearly = ! $isFree && $p->interval === 'month' && $p->price_year_minor ? [
                'price' => MenuPageData::money((int) $p->price_year_minor, $p->currency),
                'months' => max(0, (int) round(12 - $p->price_year_minor / max(1, $p->price_minor))),
            ] : null;

            $out[] = [
                'name' => $name,
                'yearly' => $yearly,
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
     * @param  \Closure(string, array<string, string>=): string  $t
     */
    private function jsonLd(string $name, array $socials, array $plans, bool $faq, bool $free, \Closure $t): string
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
            'description' => $t($free ? 'meta_description' : 'meta_description_paid'),
            'offers' => array_map(fn (array $p) => ['@type' => 'Offer', 'name' => $p['name'], 'price' => (string) $p['price_value'], 'priceCurrency' => $p['currency']], $plans) ?: null,
        ];

        $graph = [$org, array_filter($app)];
        if ($faq) {
            $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn (string $k) => [
                '@type' => 'Question', 'name' => $t("faq.items.{$k}.q"), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $t("faq.items.{$k}.a")],
            ], array_keys((array) __('landing.faq.items')))];
        }

        return (string) json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
