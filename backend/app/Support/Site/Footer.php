<?php

namespace App\Support\Site;

use App\Http\Controllers\SiteController;
use App\Models\FooterVersion;
use App\Models\Tenant;
use App\Support\MobileApp;
use App\Support\PlatformSettings;
use App\Support\SignUp\SignUp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The website footer (owner, 2026-11-18, QUESTIONS #230), managed in Super
 * Admin → Footer: link columns, contact block, social links, the Google Play
 * badge, a trust line and the bottom bar.
 *
 * Content is plain data (never HTML): `normalize()` cleans what the editor
 * sends (lengths, ids, safe links only — https, http, mailto, tel or a path on
 * this site; never javascript: or data:), Blade escapes every text. The draft
 * is a platform setting; every publish adds a FooterVersion and the newest one
 * is live. Shown on the landing page, the account pages, Help and the other
 * site pages — never on a restaurant's guest menu.
 */
final class Footer
{
    public const MAX_COLUMNS = 6;

    public const MAX_LINKS = 12;

    public const SOCIALS = ['facebook', 'instagram', 'tiktok', 'youtube', 'telegram', 'x', 'linkedin'];

    private const CACHE_KEY = 'site.footer.published';

    /**
     * Pages of this site a link can point to: key => path. The editor shows
     * them as a list, so a link never breaks when an address changes.
     */
    public const PAGES = [
        'home' => '/',
        'features' => '/#features',
        'pricing' => '/#pricing',
        'demo' => '/'.SiteController::DEMO,
        'styles' => '/support/menu-styles',
        'app' => '/support/install-app',
        'how' => '/#how',
        'faq' => '/#faq',
        'signup' => '/signup',
        'signin' => '/signin',
        'help' => '/support',
        'qr_guide' => '/support/print-materials',
        'about' => '/about',
        'contact' => '/contact',
        'terms' => '/terms',
        'privacy' => '/privacy',
        'cookies' => '/cookies',
        'delete_account' => '/support/delete-account',
    ];

    /**
     * The footer a new install starts with: four columns of real pages, the
     * contact details and social links already in the settings.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $page = fn (string $key, bool $on = true) => ['id' => $key, 'label' => __("footer.pages.{$key}"), 'page' => $key, 'url' => '', 'on' => $on];
        $s = PlatformSettings::all();
        $socials = (array) ($s['socials'] ?? []);

        return [
            'illustration' => true,
            'columns' => [
                ['id' => 'product', 'title' => __('footer.columns.product'), 'on' => true, 'links' => [
                    $page('features'), $page('pricing'), $page('demo'), $page('styles'), $page('app'),
                ]],
                ['id' => 'restaurants', 'title' => __('footer.columns.restaurants'), 'on' => true, 'links' => [
                    $page('how'), $page('signup'), $page('signin'), $page('help'), $page('qr_guide'),
                ]],
                ['id' => 'company', 'title' => __('footer.columns.company'), 'on' => true, 'links' => [
                    $page('about'), $page('contact'),
                    // Partners: off until the Super Admin adds the page's address.
                    ['id' => 'partners', 'label' => __('footer.partners'), 'page' => null, 'url' => '', 'on' => false],
                ]],
                ['id' => 'legal', 'title' => __('footer.columns.legal'), 'on' => true, 'links' => [
                    $page('terms'), $page('privacy'), $page('cookies'), $page('delete_account'),
                ]],
            ],
            'contact' => [
                'on' => true,
                'title' => __('footer.contact.title'),
                'whatsapp' => (string) ($s['upgrade_whatsapp'] ?? ''),
                'phone' => '',
                'email' => (string) ($s['support_email'] ?? ''),
                'address' => '',
                'hours' => '',
            ],
            'socials' => array_map(fn (string $n) => is_string($socials[$n] ?? null) ? (string) $socials[$n] : '', array_combine(self::SOCIALS, self::SOCIALS)),
            'app' => ['on' => true, 'play_url' => (string) (MobileApp::settings()['links']['play_store'] ?? '')],
            // Only true things: "free forever" only when sign-up gives a free plan.
            'trust' => ['on' => (SignUp::plan()->price_minor ?? 1) === 0, 'text' => __('footer.trust')],
            'bottom' => ['company' => (string) $s['name'], 'made_in' => '', 'back_to_top' => true],
        ];
    }

    /**
     * The draft in the editor: the saved draft, else the live footer.
     *
     * @return array<string, mixed>
     */
    public static function draft(): array
    {
        $draft = PlatformSettings::get('footer_draft');

        return is_array($draft) ? self::normalize($draft) : self::published();
    }

    /**
     * The live footer: the newest published version, else the defaults.
     *
     * @return array<string, mixed>
     */
    public static function published(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 3600, fn () => FooterVersion::latest('id')->value('content'));
        $content = is_string($stored) ? json_decode($stored, true) : $stored;

        // The defaults are cleaned too: they copy old settings (e.g. social links) that were never checked.
        return self::normalize(is_array($content) ? $content : self::defaults());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** True when the draft differs from the live footer. */
    public static function hasChanges(): bool
    {
        return is_array(PlatformSettings::get('footer_draft')) && self::draft() !== self::published();
    }

    /**
     * Cleans footer content from the editor (or an old version): known keys
     * only, texts trimmed and cut to length, ids made safe, links checked.
     * Unsafe links are dropped to '' (the validation already refused them
     * when they came from the editor).
     *
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    public static function normalize(array $in): array
    {
        $text = fn (mixed $v, int $max) => Str::limit(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', is_string($v) ? $v : '') ?? ''), $max, '');
        $bool = fn (mixed $v, bool $default = true) => is_bool($v) ? $v : $default;
        $id = fn (mixed $v) => is_string($v) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $v) ? $v : Str::lower(Str::random(8));

        $columns = [];
        foreach (array_slice(array_values(array_filter((array) ($in['columns'] ?? []), 'is_array')), 0, self::MAX_COLUMNS) as $c) {
            $links = [];
            foreach (array_slice(array_values(array_filter((array) ($c['links'] ?? []), 'is_array')), 0, self::MAX_LINKS) as $l) {
                $page = is_string($l['page'] ?? null) && isset(self::PAGES[$l['page']]) ? $l['page'] : null;
                $url = $page === null ? (self::safeUrl((string) ($l['url'] ?? '')) ?? '') : '';
                $links[] = ['id' => $id($l['id'] ?? null), 'label' => $text($l['label'] ?? '', 60), 'page' => $page, 'url' => $url, 'on' => $bool($l['on'] ?? null)];
            }
            $columns[] = ['id' => $id($c['id'] ?? null), 'title' => $text($c['title'] ?? '', 60), 'on' => $bool($c['on'] ?? null), 'links' => $links];
        }

        $contact = (array) ($in['contact'] ?? []);
        $email = $text($contact['email'] ?? '', 120);
        $socials = (array) ($in['socials'] ?? []);
        $play = (string) (($in['app'] ?? [])['play_url'] ?? '');

        return [
            'illustration' => $bool($in['illustration'] ?? null),
            'columns' => $columns,
            'contact' => [
                'on' => $bool($contact['on'] ?? null),
                'title' => $text($contact['title'] ?? '', 60),
                'whatsapp' => $text(preg_replace('/[^\d+ ()-]/', '', (string) ($contact['whatsapp'] ?? '')), 30),
                'phone' => $text(preg_replace('/[^\d+ ()-]/', '', (string) ($contact['phone'] ?? '')), 30),
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
                'address' => $text($contact['address'] ?? '', 160),
                'hours' => $text($contact['hours'] ?? '', 120),
            ],
            'socials' => array_map(fn (string $n) => self::isSocialUrl((string) ($socials[$n] ?? '')) ? trim((string) $socials[$n]) : '', array_combine(self::SOCIALS, self::SOCIALS)),
            'app' => ['on' => $bool(($in['app'] ?? [])['on'] ?? null), 'play_url' => self::isPlayUrl($play) ? trim($play) : ''],
            'trust' => ['on' => $bool(($in['trust'] ?? [])['on'] ?? null, false), 'text' => $text(($in['trust'] ?? [])['text'] ?? '', 120)],
            'bottom' => [
                'company' => $text(($in['bottom'] ?? [])['company'] ?? '', 80),
                'made_in' => $text(($in['bottom'] ?? [])['made_in'] ?? '', 80),
                'back_to_top' => $bool(($in['bottom'] ?? [])['back_to_top'] ?? null),
            ],
        ];
    }

    /**
     * A link the footer may show: https / http web addresses, mailto:, tel:,
     * or a path on this site ("/about", not "//evil.example"). Never
     * javascript:, data:, vbscript: or anything else. Null when not allowed.
     */
    public static function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7F<>"\'`\\\\]/', $url)) {
            return null;
        }
        if (preg_match('#^/(?!/)[A-Za-z0-9._~!$&()*+,;=:@%/?\#-]*$#', $url)) {
            return $url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) && is_string(parse_url($url, PHP_URL_HOST)) ? $url : null;
        }
        if (preg_match('/^mailto:([^?]+)$/i', $url, $m)) {
            return filter_var(rawurldecode($m[1]), FILTER_VALIDATE_EMAIL) ? $url : null;
        }
        if (preg_match('/^tel:\+?[0-9][0-9 ()-]{2,24}$/i', $url)) {
            return $url;
        }

        return null;
    }

    public static function isSocialUrl(string $url): bool
    {
        $url = trim($url);

        return $url !== '' && str_starts_with(strtolower($url), 'https://') && self::safeUrl($url) !== null;
    }

    public static function isPlayUrl(string $url): bool
    {
        return self::isSocialUrl($url) && str_starts_with(strtolower(trim($url)), 'https://play.google.com/');
    }

    /**
     * What the page shows: only parts that are on and have something in
     * them, links resolved to addresses, pages that don't exist right now
     * (no demo restaurant, sign-up closed) left out.
     *
     * @param  array<string, mixed>  $f  normalized content
     * @return array<string, mixed>
     */
    public static function view(array $f): array
    {
        $available = self::availablePages();
        $columns = [];
        foreach ($f['columns'] as $c) {
            if (! $c['on'] || $c['title'] === '') {
                continue;
            }
            $links = [];
            foreach ($c['links'] as $l) {
                $href = $l['page'] !== null ? ($available[$l['page']] ?? null) : ($l['url'] !== '' ? $l['url'] : null);
                if ($l['on'] && $l['label'] !== '' && $href !== null) {
                    $links[] = ['label' => $l['label'], 'href' => $href, 'external' => (bool) preg_match('#^https?://#i', $href)];
                }
            }
            if ($links !== []) {
                $columns[] = ['id' => $c['id'], 'title' => $c['title'], 'links' => $links];
            }
        }

        $ct = $f['contact'];
        $name = (string) PlatformSettings::get('name');
        $digits = preg_replace('/\D+/', '', $ct['whatsapp']);
        $contact = ! $ct['on'] ? null : array_filter([
            'whatsapp' => $digits !== '' ? 'https://wa.me/'.$digits.'?text='.rawurlencode(__('footer.contact.whatsapp_text', ['name' => $name])) : null,
            'phone' => $ct['phone'] !== '' ? ['href' => 'tel:'.preg_replace('/[^\d+]/', '', $ct['phone']), 'text' => $ct['phone']] : null,
            'email' => $ct['email'] !== '' ? ['href' => 'mailto:'.$ct['email'], 'text' => $ct['email']] : null,
            'address' => $ct['address'] !== '' ? $ct['address'] : null,
            'hours' => $ct['hours'] !== '' ? $ct['hours'] : null,
        ]);

        return [
            'illustration' => $f['illustration'],
            'columns' => $columns,
            'contact' => $contact === [] || $contact === null ? null : ['title' => $ct['title'] !== '' ? $ct['title'] : __('footer.contact.title'), ...$contact],
            'socials' => array_filter($f['socials']),
            'play' => $f['app']['on'] && $f['app']['play_url'] !== '' ? $f['app']['play_url'] : null,
            'trust' => $f['trust']['on'] && $f['trust']['text'] !== '' ? $f['trust']['text'] : null,
            'company' => $f['bottom']['company'] !== '' ? $f['bottom']['company'] : $name,
            'made_in' => $f['bottom']['made_in'] !== '' ? $f['bottom']['made_in'] : null,
            'back_to_top' => $f['bottom']['back_to_top'],
            'name' => $name,
        ];
    }

    /**
     * Pages that exist right now, key => address: the demo menu only when the
     * demo restaurant is live, sign-up only when it is open.
     *
     * @return array<string, string>
     */
    public static function availablePages(): array
    {
        $pages = self::PAGES;
        if (! Tenant::where('slug', SiteController::DEMO)->whereNull('suspended_at')->exists()) {
            unset($pages['demo']);
        }
        if (! PlatformSettings::get('signup_open')) {
            unset($pages['signup']);
        }

        return $pages;
    }
}
