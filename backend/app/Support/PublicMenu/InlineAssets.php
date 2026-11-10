<?php

namespace App\Support\PublicMenu;

/**
 * The guest page's CSS and JS, inlined into the HTML (no extra requests).
 * A light minifier: comments and indentation go, the code stays readable in git.
 */
class InlineAssets
{
    /** @var array<string, string> */
    private static array $memo = [];

    public static function css(): string
    {
        return self::$memo['css'] ??= trim((string) preg_replace(
            ['#/\*.*?\*/#s', '/\s*\n\s*/', '/\s*([{};:,>])\s*/', '/;}/'],
            ['', '', '$1', '}'],
            (string) file_get_contents(resource_path('public-menu/menu.css')),
        ));
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
