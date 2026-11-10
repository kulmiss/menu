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
        $lines = explode("\n", (string) file_get_contents(resource_path('public-menu/menu.js')));
        $kept = array_filter(array_map('trim', $lines), fn (string $l) => $l !== '' && ! str_starts_with($l, '//'));

        return self::$memo['js'] ??= implode("\n", $kept);
    }
}
