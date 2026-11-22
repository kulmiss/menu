<?php

namespace App\Support\Landing;

use App\Models\LandingRestaurant;
use App\Support\PlatformSettings;
use App\Support\Site\Footer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Landing 2's parts the Super Admin manages (owner, 2026-11-22): an ON/OFF switch for every
 * section, the payment methods, the real numbers, the real restaurants and the demo video.
 */
class Landing2
{
    /** Every part of the page that can be switched off (all on by default), in page order. */
    public const SECTIONS = [
        'switcher', 'hero_photo', 'pattern', 'problem', 'how', 'price_demo', 'numbers', 'styles', 'video', 'compare',
        'qr', 'poster', 'features', 'restaurants', 'pricing', 'billing', 'payments', 'faq', 'final',
        'bands', 'motion', 'languages',
    ];

    /** Fewest restaurants (with permission) before the restaurants section shows. */
    public const MIN_RESTAURANTS = 3;

    /** Icons for payment methods (our own line icons, no brand logos: QUESTIONS #255). */
    public const PAYMENT_ICONS = ['phone', 'bank', 'card', 'cash'];

    /** The built-in payment methods (owner, 2026-11-22). */
    public const PAYMENTS = [
        ['label_en' => 'telebirr', 'label_am' => 'ቴሌብር', 'icon' => 'phone', 'on' => true],
        ['label_en' => 'CBE Birr', 'label_am' => 'ሲቢኢ ብር', 'icon' => 'phone', 'on' => true],
        ['label_en' => 'Bank transfer', 'label_am' => 'የባንክ ዝውውር', 'icon' => 'bank', 'on' => true],
    ];

    /** Our own numbers, counted daily from the database; each hides below its minimum. */
    public const OWN_NUMBERS = ['restaurants' => 100, 'dishes' => 1000, 'views' => 1000];

    /** An outside number older than this many days gets a reminder in Super Admin. */
    public const STALE_DAYS = 30;

    /** The built-in demo video (backend/public/landing/video), when it is there. */
    public const VIDEO_DIR = 'landing/video';

    /** @return array<string, bool> section => on */
    public static function sections(): array
    {
        $saved = PlatformSettings::get('landing2_sections');
        $out = [];
        foreach (self::SECTIONS as $key) {
            $out[$key] = ! is_array($saved) || ! array_key_exists($key, $saved) || (bool) $saved[$key];
        }

        return $out;
    }

    /** @return list<array{label_en: string, label_am: string, icon: string, on: bool}> */
    public static function payments(): array
    {
        $saved = PlatformSettings::get('landing2_payments');
        if (! is_array($saved)) {
            return self::PAYMENTS;
        }
        $out = [];
        foreach ($saved as $p) {
            if (is_array($p) && trim((string) ($p['label_en'] ?? '')) !== '') {
                $out[] = [
                    'label_en' => trim((string) $p['label_en']),
                    'label_am' => trim((string) ($p['label_am'] ?? '')),
                    'icon' => in_array($p['icon'] ?? null, self::PAYMENT_ICONS, true) ? $p['icon'] : 'phone',
                    'on' => (bool) ($p['on'] ?? true),
                ];
            }
        }

        return $out;
    }

    /**
     * The numbers settings: our own (on, minimum) and the ones the Super Admin types.
     *
     * @return array{own: array<string, array{on: bool, min: int}>, outside: list<array{value: int, label_en: string, label_am: string, source: string, date: string, on: bool, min: int}>}
     */
    public static function numbersConfig(): array
    {
        $saved = PlatformSettings::get('landing2_numbers');
        $saved = is_array($saved) ? $saved : [];
        $own = [];
        foreach (self::OWN_NUMBERS as $key => $min) {
            $s = is_array($saved['own'][$key] ?? null) ? $saved['own'][$key] : [];
            $own[$key] = ['on' => (bool) ($s['on'] ?? true), 'min' => max(0, (int) ($s['min'] ?? $min))];
        }
        $outside = [];
        foreach (is_array($saved['outside'] ?? null) ? $saved['outside'] : [] as $n) {
            if (is_array($n) && isset($n['value'], $n['label_en'], $n['source'], $n['date'])) {
                $outside[] = [
                    'value' => max(0, (int) $n['value']),
                    'label_en' => (string) $n['label_en'],
                    'label_am' => (string) ($n['label_am'] ?? ''),
                    'source' => (string) $n['source'],
                    'date' => (string) $n['date'],
                    'on' => (bool) ($n['on'] ?? true),
                    'min' => max(0, (int) ($n['min'] ?? 0)),
                ];
            }
        }

        return ['own' => $own, 'outside' => $outside];
    }

    /**
     * Rounded down, so the page never says more than is true: 7 → "7", 43 → "40+",
     * 1,043 → "1,000+", 1,543 → "1,500+", 15,400 → "15,000+".
     */
    public static function roundDown(int $n): string
    {
        if ($n < 20) {
            return number_format(max(0, $n));
        }
        $step = 10 ** (strlen((string) $n) - 1);
        if (intdiv($n, $step) < 2) {
            $step = intdiv($step, 2);
        }

        return number_format(intdiv($n, $step) * $step).'+';
    }

    /**
     * The numbers the page shows: on, counted and at least their minimum.
     *
     * @return list<array{value: string, label: string, source: string|null}>
     */
    public static function numbers(string $locale): array
    {
        $config = self::numbersConfig();
        $counted = PlatformSettings::get('landing2_numbers_counted');
        $t = Landing2Texts::resolver($locale);
        $out = [];
        foreach ($config['own'] as $key => $c) {
            $n = is_array($counted) ? ($counted[$key] ?? null) : null;
            if ($c['on'] && is_int($n) && $n >= max(1, $c['min'])) {
                $out[] = ['value' => self::roundDown($n), 'label' => (string) $t("numbers.{$key}"), 'source' => null];
            }
        }
        foreach ($config['outside'] as $n) {
            $label = $locale === 'am' && trim($n['label_am']) !== '' ? $n['label_am'] : $n['label_en'];
            if ($n['on'] && $n['value'] >= max(1, $n['min']) && trim($label) !== '') {
                $date = self::date($n['date'], $locale);
                $out[] = ['value' => self::roundDown($n['value']), 'label' => $label, 'source' => (string) $t('numbers.source', ['source' => $n['source'], 'date' => $date])];
            }
        }

        return $out;
    }

    /** Outside numbers older than STALE_DAYS (for the reminder in Super Admin). */
    public static function staleNumbers(): int
    {
        $limit = now()->subDays(self::STALE_DAYS)->toDateString();

        return count(array_filter(self::numbersConfig()['outside'], fn (array $n) => $n['on'] && $n['date'] < $limit));
    }

    /** "2026-11-01" as "1 Nov 2026" (English) or "2026-11-01" left as it is (Amharic: no month names to guess). */
    private static function date(string $date, string $locale): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return $date;
        }

        return $locale === 'am' ? $date : date('j M Y', (int) strtotime($date));
    }

    /**
     * Restaurants with permission, in order; none until there are at least MIN_RESTAURANTS.
     *
     * @return list<array{name: string, city: string, quote: string, logo: array{src: string, srcset: string}|null}>
     */
    public static function restaurants(string $locale): array
    {
        $rows = LandingRestaurant::query()->where('is_active', true)->where('permission', true)->orderBy('sort')->orderBy('id')->get();
        $out = [];
        foreach ($rows as $r) {
            $quote = $locale === 'am' && trim((string) $r->quote_am) !== '' ? (string) $r->quote_am : (string) $r->quote_en;
            if (trim($r->name) !== '' && trim($quote) !== '') {
                $out[] = ['name' => $r->name, 'city' => (string) $r->city, 'quote' => $quote, 'logo' => $r->logoUrl()];
            }
        }

        return count($out) >= self::MIN_RESTAURANTS ? $out : [];
    }

    /**
     * The demo video: the Super Admin's upload, else the built-in one; null while there is none.
     *
     * @return array{mp4: string, poster: string, captions: array{en: string|null, am: string|null}, custom: bool}|null
     */
    public static function video(): ?array
    {
        $saved = PlatformSettings::get('landing2_video');
        if (is_array($saved) && isset($saved['folder'])) {
            $disk = Storage::disk('public');
            $f = (string) $saved['folder'];
            if ($disk->exists("{$f}/video.mp4") && $disk->exists("{$f}/poster.webp")) {
                return [
                    'mp4' => $disk->url("{$f}/video.mp4"),
                    'poster' => $disk->url("{$f}/poster.webp"),
                    'captions' => [
                        'en' => $disk->exists("{$f}/en.vtt") ? $disk->url("{$f}/en.vtt") : null,
                        'am' => $disk->exists("{$f}/am.vtt") ? $disk->url("{$f}/am.vtt") : null,
                    ],
                    'custom' => true,
                ];
            }
        }
        $dir = self::VIDEO_DIR;
        if (! is_file(public_path("{$dir}/demo.mp4")) || ! is_file(public_path("{$dir}/poster.webp"))) {
            return null;
        }

        return [
            'mp4' => asset("{$dir}/demo.mp4"),
            'poster' => asset("{$dir}/poster.webp"),
            'captions' => [
                'en' => is_file(public_path("{$dir}/en.vtt")) ? asset("{$dir}/en.vtt") : null,
                'am' => is_file(public_path("{$dir}/am.vtt")) ? asset("{$dir}/am.vtt") : null,
            ],
            'custom' => false,
        ];
    }

    /**
     * The live footer for a language. Amharic: the built-in texts in Amharic, and the texts the
     * Super Admin typed in Content → Footer translated in Super Admin → Landing 2.
     *
     * @return array<string, mixed> Footer::view() data
     */
    public static function footer(string $locale): array
    {
        $view = Footer::view(Footer::published());
        if ($locale !== 'am') {
            return $view;
        }
        $map = self::footerMap();
        $tr = fn (?string $s) => $s === null ? null : ($map[$s] ?? $s);
        foreach ($view['columns'] as $i => $c) {
            $view['columns'][$i]['title'] = $tr($c['title']);
            foreach ($c['links'] as $j => $l) {
                $view['columns'][$i]['links'][$j]['label'] = $tr($l['label']);
            }
        }
        if ($view['contact'] !== null) {
            foreach (['title', 'address', 'hours'] as $k) {
                if (isset($view['contact'][$k]) && is_string($view['contact'][$k])) {
                    $view['contact'][$k] = $tr($view['contact'][$k]);
                }
            }
        }
        $view['trust'] = $tr($view['trust']);
        $view['made_in'] = $tr($view['made_in']);

        return $view;
    }

    /**
     * English footer text => Amharic: the built-in footer texts (lang/en/footer.php → lang/am/footer.php),
     * then (unless $own is false) the Super Admin's own translations.
     *
     * @return array<string, string>
     */
    public static function footerMap(bool $own = true): array
    {
        $en = array_filter(Arr::dot((array) trans('footer', [], 'en')), 'is_string');
        $am = array_filter(Arr::dot((array) trans('footer', [], 'am')), 'is_string');
        $map = [];
        foreach ($en as $key => $text) {
            if (isset($am[$key]) && $am[$key] !== $text) {
                $map[$text] = $am[$key];
            }
        }
        $saved = $own ? PlatformSettings::get('landing2_footer_am') : [];
        foreach (is_array($saved) ? $saved : [] as $from => $to) {
            if (is_string($from) && is_string($to) && trim($to) !== '') {
                $map[$from] = trim($to);
            }
        }

        return $map;
    }

    /**
     * The texts of the live footer that the Amharic page needs (the editor lists them).
     *
     * @return list<string>
     */
    public static function footerTexts(): array
    {
        $view = Footer::view(Footer::published());
        $out = [];
        foreach ($view['columns'] as $c) {
            $out[] = $c['title'];
            foreach ($c['links'] as $l) {
                $out[] = $l['label'];
            }
        }
        foreach (['title', 'address', 'hours'] as $k) {
            if (is_string($view['contact'][$k] ?? null)) {
                $out[] = $view['contact'][$k];
            }
        }
        $out[] = $view['trust'];
        $out[] = $view['made_in'];

        return array_values(array_unique(array_filter($out, fn ($s) => is_string($s) && trim($s) !== '')));
    }
}
