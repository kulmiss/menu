<?php

namespace App\Support\Campaigns;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Campaign text: a small Markdown subset (**bold**, *italic*, [links](https://…),
 * "- " lists, blank lines between paragraphs) with placeholders. Raw HTML is
 * escaped; placeholder values are escaped too, so a restaurant called
 * "*Best* <b>Café</b>" prints exactly that.
 */
class Body
{
    public const PLACEHOLDERS = ['restaurant_name', 'owner_name', 'plan', 'menu_link'];

    /** @param  array<string, string>  $vars */
    public static function fill(string $text, array $vars): string
    {
        return (string) preg_replace_callback('/\{(restaurant_name|owner_name|plan|menu_link)\}/', function ($m) use ($vars) {
            $value = (string) ($vars[$m[1]] ?? '');

            return $m[1] === 'menu_link' ? $value : self::escape($value);
        }, $text);
    }

    /** @param  array<string, string>  $vars */
    public static function html(string $text, array $vars): HtmlString
    {
        return new HtmlString(Str::markdown(self::fill($text, $vars), ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }

    /** @param  array<string, string>  $vars */
    public static function subject(string $subject, array $vars): string
    {
        return (string) preg_replace_callback('/\{(restaurant_name|owner_name|plan|menu_link)\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $subject);
    }

    private static function escape(string $value): string
    {
        return addcslashes($value, '\\`*_{}[]()#+-.!<>|~');
    }
}
