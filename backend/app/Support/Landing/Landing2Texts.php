<?php

namespace App\Support\Landing;

use App\Support\PlatformSettings;
use Illuminate\Support\Arr;
use Illuminate\Translation\MessageSelector;

/**
 * Every text of Landing 2, in English and Amharic (owner, 2026-11-22). The defaults are
 * lang/en/landing2.php and lang/am/landing2.php; the setting "landing2_texts" holds only
 * the texts the Super Admin changed: ['en' => [key => text], 'am' => [key => text]].
 *
 * English texts that Landing 1 also has (the problem, paper vs QR, the questions, the main
 * button) keep a change made in Landing 1's editor until Landing 2 gets its own.
 */
class Landing2Texts
{
    public const LOCALES = ['en', 'am'];

    /** Parts that are the same text on Landing 1 (lang/en/landing.php, same keys). */
    private const SHARED = [
        'meta_description', 'meta_description_paid', 'create_account', 'contact_whatsapp', 'contact_email', 'contact_text',
        'problem', 'compare', 'faq.title', 'faq.items',
    ];

    /** Longer texts (answers, lines under a headline). */
    private const LONG = ['meta_description', 'meta_description_paid', 'text', 'a', 'label', 'note', 'contact_text'];

    /** @var array<string, array<string, string>> */
    private static array $defaults = [];

    /**
     * Every default text of a language, as "dot.key" => text, in page order. A key the
     * Amharic file does not have yet uses the English text (a test keeps the files equal).
     *
     * @return array<string, string>
     */
    public static function defaults(string $locale = 'en'): array
    {
        $locale = self::locale($locale);
        if (isset(self::$defaults[$locale])) {
            return self::$defaults[$locale];
        }
        $flat = fn (string $l) => array_filter(Arr::dot((array) trans('landing2', [], $l)), 'is_string');
        $en = $flat('en');

        return self::$defaults[$locale] = $locale === 'en' ? $en : array_intersect_key([...$en, ...$flat($locale)], $en);
    }

    public static function max(string $key): int
    {
        return in_array(last(explode('.', $key)), self::LONG, true) ? 400 : 160;
    }

    /**
     * The texts the Super Admin changed in one language (known keys only).
     *
     * @return array<string, string>
     */
    public static function overrides(string $locale): array
    {
        $saved = PlatformSettings::get('landing2_texts');
        $mine = is_array($saved) && is_array($saved[self::locale($locale)] ?? null) ? $saved[self::locale($locale)] : [];
        $out = [];
        foreach ($mine as $key => $text) {
            if (is_string($key) && is_string($text) && trim($text) !== '' && array_key_exists($key, self::defaults())) {
                $out[$key] = $text;
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
    public static function clean(string $locale, array $texts): array
    {
        $defaults = self::defaults($locale);
        $out = [];
        foreach ($texts as $key => $text) {
            $text = trim((string) $text);
            if (array_key_exists($key, $defaults) && $text !== '' && $text !== $defaults[$key]) {
                $out[$key] = $text;
            }
        }

        return $out;
    }

    public static function isShared(string $key): bool
    {
        foreach (self::SHARED as $shared) {
            if ($key === $shared || str_starts_with($key, $shared.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The text the page shows for one key: the Super Admin's change, else (English, shared
     * with Landing 1) Landing 1's change, else the default.
     *
     * @return array<string, string> key => text, every key
     */
    public static function all(string $locale): array
    {
        $locale = self::locale($locale);
        $texts = [...self::defaults($locale)];
        if ($locale === 'en') {
            foreach (LandingTexts::overrides() as $key => $text) {
                if (array_key_exists($key, $texts) && self::isShared($key)) {
                    $texts[$key] = $text;
                }
            }
        }

        return [...$texts, ...self::overrides($locale)];
    }

    /**
     * A function for the view: a text (with :name replaced), or a whole part as an array
     * (e.g. "hero.points"). A key Landing 2 does not have falls back to Landing 1's texts
     * (English), for the few the shared page data asks for (e.g. the chat message).
     *
     * @return \Closure(string, array<string, string|int>=): mixed
     */
    public static function resolver(string $locale): \Closure
    {
        $locale = self::locale($locale);
        $texts = self::all($locale);
        $tree = Arr::undot($texts);
        $landing1 = LandingTexts::resolver();
        $replace = function (string $text, array $pairs): string {
            $map = [];
            foreach ($pairs as $name => $value) {
                $map[':'.$name] = (string) $value;
            }

            return strtr($text, $map);
        };

        return function (string $key, array $pairs = []) use ($texts, $tree, $landing1, $replace) {
            if (isset($texts[$key])) {
                return $replace($texts[$key], $pairs);
            }
            $part = Arr::get($tree, $key);
            if (is_array($part)) {
                return self::walk($part, fn (string $t) => $replace($t, $pairs));
            }

            return $landing1($key, $pairs);
        };
    }

    /** One text with a count ("{1} 1 month free|[2,*] :n months free"). */
    public static function choice(string $locale, string $key, int $n): string
    {
        $locale = self::locale($locale);
        $line = self::all($locale)[$key] ?? $key;

        return str_replace(':n', (string) $n, (new MessageSelector)->choose($line, $n, $locale));
    }

    private static function locale(string $locale): string
    {
        return in_array($locale, self::LOCALES, true) ? $locale : 'en';
    }

    /**
     * @param  array<int|string, mixed>  $part
     * @return array<int|string, mixed>
     */
    private static function walk(array $part, \Closure $fn): array
    {
        foreach ($part as $k => $v) {
            $part[$k] = is_array($v) ? self::walk($v, $fn) : $fn((string) $v);
        }

        return $part;
    }

    /** Language files whose Amharic lines (plan lines, footer) the review CSV can change. */
    public const LINE_GROUPS = ['landing' => 'pricing', 'footer' => null];

    /**
     * Every Amharic line of those files the CSV covers: "landing.pricing.free" => [English, Amharic].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function lines(): array
    {
        $out = [];
        foreach (self::LINE_GROUPS as $group => $part) {
            $en = Arr::dot((array) trans($part === null ? $group : "{$group}.{$part}", [], 'en'));
            $am = Arr::dot((array) trans($part === null ? $group : "{$group}.{$part}", [], 'am'));
            $prefix = $part === null ? $group : "{$group}.{$part}";
            foreach ($en as $key => $text) {
                if (is_string($text) && is_string($am[$key] ?? null)) {
                    $out["{$prefix}.{$key}"] = [$text, $am[$key]];
                }
            }
        }

        return $out;
    }

    /**
     * Puts the Amharic lines saved from the review CSV over the language files, for this request
     * (the Amharic page only). The files are loaded first, so the rest of each file stays.
     */
    public static function applyLines(): void
    {
        $saved = PlatformSettings::get('landing2_lines');
        $am = is_array($saved['am'] ?? null) ? $saved['am'] : [];
        $known = self::lines();
        $translator = app('translator');
        foreach (array_keys(self::LINE_GROUPS) as $group) {
            $translator->load('*', $group, 'am');
        }
        $add = [];
        foreach ($am as $key => $text) {
            if (is_string($key) && is_string($text) && trim($text) !== '' && isset($known[$key])) {
                $add[$key] = $text;
            }
        }
        $translator->addLines($add, 'am');
    }

    /** For tests: forget the defaults read so far. */
    public static function flush(): void
    {
        self::$defaults = [];
    }
}
