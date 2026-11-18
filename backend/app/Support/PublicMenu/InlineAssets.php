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
     * a page never carries the other styles).
     */
    public static function css(string $style = 'grid'): string
    {
        $style = in_array($style, TenantAppearance::STYLES, true) ? $style : 'grid';

        return self::$memo['css.'.$style] ??= (string) preg_replace_callback(
            '/url\(font:([a-z0-9-]+)\)/',
            fn (array $m) => 'url('.self::fontUrl($m[1]).')',
            self::minifyCss(file_get_contents(resource_path('public-menu/base.css'))."\n".file_get_contents(resource_path('public-menu/style-'.$style.'.css'))),
        );
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
     * public/menu-assets/sheet.{hash}.js so it can be cached for a year.
     */
    public static function sheetUrl(): string
    {
        return self::$memo['sheet'] ??= self::publish(self::minifyJs((string) file_get_contents(resource_path('public-menu/sheet.js'))), 'sheet', 'js');
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
