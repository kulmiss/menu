<?php

namespace App\Support\PublicMenu;

/**
 * Left-to-right or right-to-left for the guest page (style H · Arabic, owner
 * 2026-11-18). "auto" looks at the restaurant name, the category names and
 * the dish names: right-to-left when more of their letters are in a
 * right-to-left script (Arabic, Hebrew, Syriac, Thaana, N'Ko) than in any
 * other. Digits, spaces and punctuation don't count.
 */
final class MenuDirection
{
    /**
     * @param  'auto'|'ltr'|'rtl'|string  $setting
     * @param  list<array{categories: list<array{name: string, items: list<array{name: string}>}>}>  $menus
     * @return 'ltr'|'rtl'
     */
    public static function for(string $setting, string $restaurant, array $menus): string
    {
        if ($setting === 'rtl' || $setting === 'ltr') {
            return $setting;
        }
        $texts = [$restaurant];
        foreach ($menus as $m) {
            foreach ($m['categories'] as $c) {
                $texts[] = $c['name'];
                foreach ($c['items'] as $i) {
                    $texts[] = $i['name'];
                }
            }
        }

        return self::isRtl(implode(' ', $texts)) ? 'rtl' : 'ltr';
    }

    public static function isRtl(string $text): bool
    {
        $rtl = preg_match_all('/[\p{Arabic}\p{Hebrew}\p{Syriac}\p{Thaana}\p{Nko}]/u', $text);
        $letters = preg_match_all('/\p{L}/u', $text);

        return $rtl > 0 && $rtl * 2 > $letters;
    }
}
