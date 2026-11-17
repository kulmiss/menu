<?php

namespace App\Support\Landing;

use App\Support\PlatformSettings;
use Illuminate\Support\Arr;

/**
 * Every text of the landing page can be changed in Super Admin → Landing
 * page (owner, 2026-11-17). The defaults stay in lang/en/landing.php; the
 * setting "landing_texts" only holds the texts the Super Admin changed
 * (key => text). An empty text means "use the default".
 */
class LandingTexts
{
    /** Parts of lang/en/landing.php that are page texts (not plan lines or link names). */
    private const EDITABLE = [
        'meta_title', 'meta_description', 'meta_description_paid',
        'nav.demo', 'nav.how', 'nav.features', 'nav.pricing', 'nav.faq',
        'sign_in', 'create_account', 'contact_whatsapp', 'contact_email', 'contact_text',
        'hero', 'problem', 'demo', 'how', 'features', 'compare',
        'pricing.title', 'pricing.subtitle', 'pricing.popular', 'pricing.start_paid', 'pricing.upgrade_note',
        'testimonials.title', 'faq', 'cta', 'chat', 'footer.about',
    ];

    /** Longer texts (answers, lines under a headline). */
    private const LONG = ['meta_description', 'meta_description_paid', 'subline', 'text', 'text_paid', 'a', 'upgrade_note', 'closed', 'scan_text', 'contact_text'];

    /** @var array<string, string>|null */
    private static ?array $defaults = null;

    /**
     * The English default of every editable text, in page order.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        if (self::$defaults !== null) {
            return self::$defaults;
        }
        $all = (array) trans('landing', [], 'en');
        $out = [];
        foreach (self::EDITABLE as $key) {
            $value = Arr::get($all, $key);
            if (is_array($value)) {
                foreach (Arr::dot($value) as $sub => $text) {
                    if (is_string($text)) {
                        $out["{$key}.{$sub}"] = $text;
                    }
                }
            } elseif (is_string($value)) {
                $out[$key] = $value;
            }
        }

        return self::$defaults = $out;
    }

    public static function max(string $key): int
    {
        return in_array(last(explode('.', $key)), self::LONG, true) ? 400 : 160;
    }

    /**
     * The texts the Super Admin changed. The two texts of the first version
     * (headline, subline) count as changed texts too.
     *
     * @return array<string, string>
     */
    public static function overrides(): array
    {
        $saved = PlatformSettings::get('landing_texts');
        $out = [];
        foreach (is_array($saved) ? $saved : [] as $key => $text) {
            if (is_string($key) && is_string($text) && trim($text) !== '' && array_key_exists($key, self::defaults())) {
                $out[$key] = $text;
            }
        }
        foreach (['hero.headline' => 'landing_headline', 'hero.subline' => 'landing_subline'] as $key => $legacy) {
            $old = trim((string) PlatformSettings::get($legacy));
            if (! isset($out[$key]) && $old !== '') {
                $out[$key] = $old;
            }
        }

        return $out;
    }

    /**
     * Keeps only known keys, trims, drops texts equal to the default or empty.
     *
     * @param  array<string, mixed>  $texts
     * @return array<string, string>
     */
    public static function clean(array $texts): array
    {
        $defaults = self::defaults();
        $out = [];
        foreach ($texts as $key => $text) {
            $text = trim((string) $text);
            if (array_key_exists($key, $defaults) && $text !== '' && $text !== $defaults[$key]) {
                $out[$key] = $text;
            }
        }

        return $out;
    }

    /**
     * A function for the view: the changed text, else the translated default.
     *
     * @return \Closure(string, array<string, string>=): string
     */
    public static function resolver(): \Closure
    {
        $overrides = self::overrides();

        return function (string $key, array $replace = []) use ($overrides): string {
            if (! isset($overrides[$key])) {
                return (string) __("landing.{$key}", $replace);
            }
            $pairs = [];
            foreach ($replace as $name => $value) {
                $pairs[':'.$name] = (string) $value;
            }

            return strtr($overrides[$key], $pairs);
        };
    }
}
