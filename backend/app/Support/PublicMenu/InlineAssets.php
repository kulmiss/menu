<?php

namespace App\Support\PublicMenu;

use App\Models\TenantAppearance;

/**
 * The guest page's CSS and JS, inlined into the HTML (no extra requests).
 * A light minifier: comments and indentation go, the code stays readable in git.
 */
class InlineAssets
{
    /** @var array<string, string> */
    private static array $memo = [];

    /**
     * The shared CSS plus the CSS of one menu style only (owner, 2026-11-11:
     * a page never carries the other styles): base.css, then the "My order" "+"
     * (order.css) when My order is on, then the style's own CSS. With My order off
     * the style's own "+" rules (.ad, .ao) are left out too (QUESTIONS #236).
     */
    public static function css(string $style = 'grid', bool $order = true): string
    {
        $style = in_array($style, TenantAppearance::STYLES, true) ? $style : 'grid';

        $key = 'css.'.$style.($order ? '+o' : '');
        if (! isset(self::$memo[$key])) {
            $css = self::minifyCss(file_get_contents(resource_path('public-menu/base.css'))."\n"
                .($order ? file_get_contents(resource_path('public-menu/order.css'))."\n" : '')
                .file_get_contents(resource_path('public-menu/style-'.$style.'.css')));
            self::$memo[$key] = (string) preg_replace_callback(
                '/url\(font:([a-z0-9-]+)\)/',
                fn (array $m) => 'url('.self::fontUrl($m[1]).')',
                $order ? $css : self::withoutSelectors($css, '/\.a[do](?![\w-])/'),
            );
        }

        return self::$memo[$key];
    }

    /**
     * Minified CSS without the selectors that match $pattern: a rule loses those
     * selectors, a rule (or an @media block) left with none goes. Other at-rules
     * (@font-face, @keyframes) are kept as they are.
     */
    public static function withoutSelectors(string $css, string $pattern): string
    {
        $out = '';
        $n = strlen($css);
        $i = 0;
        while ($i < $n) {
            $open = strpos($css, '{', $i);
            if ($open === false) {
                return $out.substr($css, $i);
            }
            $head = substr($css, $i, $open - $i);
            $close = self::blockEnd($css, $open);
            $body = substr($css, $open + 1, $close - $open - 1);
            $i = $close + 1;
            if (str_starts_with($head, '@media') || str_starts_with($head, '@supports') || str_starts_with($head, '@container')) {
                $inner = self::withoutSelectors($body, $pattern);
                $out .= $inner === '' ? '' : $head.'{'.$inner.'}';
            } elseif (str_starts_with($head, '@')) {
                $out .= $head.'{'.$body.'}';
            } else {
                $kept = array_filter(self::splitSelectors($head), fn (string $sel) => preg_match($pattern, $sel) !== 1);
                $out .= $kept === [] ? '' : implode(',', $kept).'{'.$body.'}';
            }
        }

        return $out;
    }

    /** The position of the "}" that closes the block opened at $open (strings skipped). */
    private static function blockEnd(string $css, int $open): int
    {
        $depth = 0;
        $n = strlen($css);
        for ($i = $open; $i < $n; $i++) {
            $c = $css[$i];
            if ($c === '"' || $c === "'") {
                $j = $i + 1;
                while ($j < $n && $css[$j] !== $c) {
                    $j += $css[$j] === '\\' ? 2 : 1;
                }
                $i = $j;
            } elseif ($c === '{') {
                $depth++;
            } elseif ($c === '}' && --$depth === 0) {
                return $i;
            }
        }

        return $n - 1;
    }

    /** @return list<string> a selector list split on the commas outside brackets */
    private static function splitSelectors(string $head): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $n = strlen($head);
        for ($i = 0; $i < $n; $i++) {
            $c = $head[$i];
            $depth += $c === '(' || $c === '[' ? 1 : ($c === ')' || $c === ']' ? -1 : 0);
            if ($c === ',' && $depth === 0) {
                $parts[] = substr($head, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($head, $start);

        return $parts;
    }

    /**
     * A style's web font (resources/public-menu/fonts, only style H has one), written
     * once per version to public/menu-assets/{name}.{hash}.woff2 so it can be cached
     * for a year; `url(font:name)` in a style's CSS becomes this address.
     */
    public static function fontUrl(string $name): string
    {
        return self::$memo['font.'.$name] ??= self::publish(
            (string) file_get_contents(resource_path('public-menu/fonts/'.$name.'.woff2')),
            $name,
            'woff2',
        );
    }

    /**
     * The header picture of a style with artwork (M–Q, resources/public-menu/art/{style}.svg):
     * original drawings, inlined (no request), shown when the restaurant has no banner and
     * keeps the decoration on (QUESTIONS #232). Empty for the other styles.
     */
    public static function art(string $style): string
    {
        if (! in_array($style, TenantAppearance::ART_STYLES, true)) {
            return '';
        }

        return self::$memo['art.'.$style] ??= trim((string) preg_replace('/\s*\n\s*/', '', (string) file_get_contents(resource_path('public-menu/art/'.$style.'.svg'))));
    }

    /** Writes a file under public/menu-assets with its content hash in the name (atomic, once). */
    private static function publish(string $content, string $name, string $extension): string
    {
        $file = $name.'.'.substr(md5($content), 0, 10).'.'.$extension;
        $path = public_path('menu-assets/'.$file);
        if (! is_file($path)) {
            @mkdir(dirname($path), 0755, true);
            $tmp = $path.'.'.bin2hex(random_bytes(4));
            file_put_contents($tmp, $content);
            rename($tmp, $path);
        }

        return '/menu-assets/'.$file;
    }

    public static function minifyCss(string $src): string
    {
        return trim((string) preg_replace(
            ['#/\*.*?\*/#s', '/\s*\n\s*/', '/\s*([{};,>])\s*/', '/([{;]\s*[-a-z]+)\s*:\s*/', '/;}/'],
            ['', '', '$1', '$1:', '}'],
            $src,
        ));
    }

    /**
     * The item sheet script (loaded on demand), written once per version to
     * public/menu-assets/sheet.{hash}.js so it can be cached for a year. With
     * "My order" on, the same file also holds the list (order.js) — still one
     * request; with it off the page never gets that code (QUESTIONS #236).
     */
    public static function sheetUrl(bool $order = true): string
    {
        return self::$memo['sheet'.($order ? '+o' : '')] ??= self::publish(
            self::minifyJs((string) file_get_contents(resource_path('public-menu/sheet.js'))
                .($order ? "\n".file_get_contents(resource_path('public-menu/order.js')) : '')),
            $order ? 'sheet-o' : 'sheet',
            'js',
        );
    }

    public static function js(): string
    {
        return self::$memo['js'] ??= self::minifyJs(
            file_get_contents(resource_path('public-menu/hours.js'))."\n".file_get_contents(resource_path('public-menu/menu.js'))
        );
    }

    /**
     * Removes comments and the whitespace that isn't needed between tokens.
     * Strings are copied as they are; whitespace between two word characters
     * becomes one space, and a line break stays where it could end a
     * statement (so automatic semicolons keep working).
     */
    public static function minifyJs(string $src): string
    {
        $out = '';
        $n = strlen($src);
        $word = fn (string $c): bool => $c !== '' && (ctype_alnum($c) || $c === '_' || $c === '$');
        for ($i = 0; $i < $n; $i++) {
            $c = $src[$i];
            if ($c === "'" || $c === '"' || $c === '`') {
                $j = $i + 1;
                while ($j < $n && $src[$j] !== $c) {
                    $j += $src[$j] === '\\' ? 2 : 1;
                }
                $out .= substr($src, $i, $j - $i + 1);
                $i = $j;

                continue;
            }
            // Line comments start with "//" after whitespace or at a line start.
            if ($c === '/' && ($src[$i + 1] ?? '') === '/' && ($i === 0 || ctype_space($src[$i - 1]))) {
                while ($i < $n && $src[$i] !== "\n") {
                    $i++;
                }
                $i--;

                continue;
            }
            if (ctype_space($c)) {
                $newline = false;
                while ($i < $n && ctype_space($src[$i])) {
                    $newline = $newline || $src[$i] === "\n";
                    $i++;
                }
                $i--;
                $prev = substr($out, -1);
                $next = $src[$i + 1] ?? '';
                if ($word($prev) && $word($next)) {
                    $out .= $newline ? "\n" : ' ';
                } elseif ($newline && $prev !== '' && ! in_array($prev, ['{', '(', '[', ',', ';', ':', '=', '+', '-', '*', '?', '|', '&', '!', '<', '>'], true)
                    && ! in_array($next, ['}', ')', ']', ',', ';', ':', '.', '?', '=', '+', '-', '*', '|', '&', '<', '>'], true)) {
                    $out .= "\n";
                }

                continue;
            }
            $out .= $c;
        }

        return trim($out);
    }
}
