<?php

namespace App\Support\PublicMenu;

/**
 * Category icons of the menu styles M–T (owner, 2026-11-19 and -21, QUESTIONS #232, #243):
 * small line drawings made for this project (24 × 24, drawn in the text colour
 * of the style). The icon follows the category's name in any of its languages
 * ("Coffee", "ቡና", "Kalluun", "قهوة"); a name with no known word gets the next
 * icon of the style's own set, so neighbouring categories differ.
 *
 * An icon set on the category (API field "icon", e.g. "fish") wins over the guess; a
 * category photo uploaded by the restaurant (Menu editor) replaces the icon.
 * Only the icons a page uses are put in it, once, as <symbol>s.
 */
final class CategoryIcons
{
    /** Path data per icon (stroke only, round ends). */
    public const PATHS = [
        'cup' => 'M4 9h12v4.5A5.5 5.5 0 0 1 10.5 19h-1A5.5 5.5 0 0 1 4 13.5zM16 10.5h1.5a2.5 2.5 0 0 1 0 5H16M3 21.5h14M8 6.5c0-1 1-1.5 1-2.5M12 6.5c0-1 1-1.5 1-2.5',
        'jebena' => 'M12 21c-4 0-6-2.6-6-6 0-3.4 2.4-5.8 5-6.6V5.5a1 1 0 0 1 2 0v2.9c2.6.8 5 3.2 5 6.6 0 3.4-2 6-6 6zM13 8.6c1.5-2 3.6-2.8 5.6-2.2M17.6 12.6h.9a2.2 2.2 0 0 1 0 4.4h-1.3',
        'leaf' => 'M5 19C5 10 10 5 19 5c0 9-5 14-14 14zM5 19l8-8M9.5 15h3M12.5 12V9.5',
        'cake' => 'M4 20h16M5 20v-7h14v7M5 15.5c2 1.3 3.5 1.3 5 0s3.5-1.3 5 0 3 1.3 4 0M12 13V9.5M12 6.8c-.8-.9-.8-1.8 0-2.8.8 1 .8 1.9 0 2.8z',
        'bread' => 'M5 11.5A3.8 3.8 0 0 1 7.6 5h8.8a3.8 3.8 0 0 1 2.6 6.5V19a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1zM9.5 9.5l1 1.5M13 9.5l1 1.5',
        'egg' => 'M12 3.5c-3.4 0-6 4.8-6 8.9a6 6 0 0 0 12 0c0-4.1-2.6-8.9-6-8.9zM12 16a2.6 2.6 0 0 1-2.6-2.6',
        'glass' => 'M7 7h10l-1.4 13H8.4zM7.4 11h9.2M13.5 7l2.6-4h2',
        'bowl' => 'M3 11h18a9 9 0 0 1-18 0zM9 20.5h6M8 8c.5-1.6 1.6-2.4 3-2.4M13 8c.6-2.2 2.2-3.5 4.4-3.5',
        'flame' => 'M12 3c.8 3.5 5 5.2 5 10a5 5 0 0 1-10 0c0-2.5 1.5-4 2.5-5 0 1.8.8 2.8 1.8 2.8 0-3.2-.6-5 .7-7.8zM12 18.2a1.9 1.9 0 0 1-1.9-1.9c0-1.3 1.1-1.8 1.9-3.1.8 1.3 1.9 1.8 1.9 3.1a1.9 1.9 0 0 1-1.9 1.9z',
        'skewer' => 'M3 21L21 3M6.6 13.4l4 4M9.3 10.7l4 4M12 8l4 4M5.3 14.7l4-4 4 4-4 4z',
        'fish' => 'M2.5 12c3.6-5.2 10.6-5.2 14.2 0-3.6 5.2-10.6 5.2-14.2 0zM16.7 12l4.8-3.8v7.6zM7.2 11.2v.1M11 9.2c1 1.8 1 3.8 0 5.6',
        'shrimp' => 'M8 19.5c-3.6-1.8-4.6-7.3-1.4-10.7 3.3-3.5 10.2-3.6 12.2.4 1 2.2-.2 4.3-2.4 4.3h-4.2c-2.3 0-3.3 2.6-1.6 4.2M18.3 7.6L21.5 4M15.4 5.7L17 2.5M9.5 9.5v4.2M13 8.8v4.6',
        'cloche' => 'M3 17h18M4.8 17a7.2 7.2 0 0 1 14.4 0M12 9.8V8M10 7.6h4M2 20.5h20',
        'wine' => 'M8 3h8l-.4 5a3.6 3.6 0 0 1-7.2 0zM8.2 6.5h7.6M12 11.6V20M8.5 20.5h7',
        'star' => 'M12 3.2l2.6 5.5 6 .8-4.4 4.1 1.1 6L12 16.7l-5.3 2.9 1.1-6-4.4-4.1 6-.8z',
        'plate' => 'M12 20a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM1.8 6v5M1.8 13.5v6.5M22.2 6v14M20.4 6v3.6a1.8 1.8 0 0 0 3.6 0V6',
        'rice' => 'M3.5 12.5h17a8.5 8.5 0 0 1-17 0zM6.5 12.5c.5-2.6 2.7-4.4 5.5-4.4s5 1.8 5.5 4.4M9 9.5l.6.9M12 8.4v1M15 9.5l-.6.9',
        'pizza' => 'M12 21L3.5 6.5C8.6 3.6 15.4 3.6 20.5 6.5zM5.4 9.8c4-2 9.2-2 13.2 0M9.5 12.4h.1M13.6 11.3h.1M12 16.2h.1',
        // R–T (owner, 2026-11-21, QUESTIONS #243).
        'burger' => 'M4 11a8 6 0 0 1 16 0zM3 14.5h18M4 17.5h16a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3zM8.5 8.5h.01M12 7.5h.01M15.5 8.5h.01',
        'fries' => 'M6 10h12l-1.6 10.5H7.6zM7.5 10l-1-6.5M10 10V3M14 10V3.5M16.5 10l1-6M6.6 13.5c3.5 1.2 7.3 1.2 10.8 0',
        'drumstick' => 'M10 14l-4.6 4.6a1.9 1.9 0 1 0 2.2 2.2 1.9 1.9 0 1 0 2.2-2.2L14 14M10 14a5.5 5.5 0 1 1 4 0',
        'injera' => 'M12 19c5 0 9.5-3.1 9.5-7S17 5 12 5 2.5 8.1 2.5 12 7 19 12 19zM8 11h.01M11.5 9h.01M15 10.5h.01M9.5 14.5h.01M13 13h.01M16.5 14h.01M12 16.5h.01',
        'pot' => 'M4 11h16M5 11c0 4.5 3 8 7 8s7-3.5 7-8M3 11h1M20 11h1M8 21.5h8M9 8c-.8-1 .8-1.8 0-3M12 7.5c-.8-1 .8-1.8 0-3M15 8c-.8-1 .8-1.8 0-3',
        'citrus' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 17.5a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11zM12 6.5v11M6.5 12h11M8.1 8.1l7.8 7.8M15.9 8.1l-7.8 7.8',
        'tallglass' => 'M7 6h10l-1.2 15H8.2zM7.4 11h9.2M7.8 15.5h8.4M13 6l2-4h2.5',
    ];

    /**
     * Words checked first in one style only (R–T), so the styles before them keep
     * every icon they had (e.g. "Burgers" stays a flame in N · Grill, a burger in R).
     */
    private const STYLE_WORDS = [
        'burger' => [
            'burger' => ['burger', 'በርገር', 'برجر', 'برغر', 'baargar'],
            'drumstick' => ['chicken', 'wings', 'ዶሮ', 'digaag', 'دجاج', 'nugget', 'tender'],
            'fries' => ['fries', 'chips', 'ፍራይ', 'side', 'loaded', 'snack'],
        ],
        'injera' => [
            'injera' => ['injera', 'እንጀራ', 'beyaynetu', 'በያይነቱ', 'combo', 'platter', 'firfir', 'ፍርፍር'],
            'pot' => ['wot', 'wat', 'ወጥ', 'stew', 'shekla', 'ሸክላ', 'fasting', 'tsom', 'ፆም', 'ጾም', 'shiro', 'ሽሮ'],
        ],
        'juice' => [
            'tallglass' => ['spris', 'ስፕሪስ', 'layered', 'smoothie', 'shake', 'ሼክ', 'milkshake'],
            'citrus' => ['fresh', 'fruit', 'ፍራፍሬ', 'lemon', 'orange', 'citrus', 'فواكه', 'miro'],
        ],
    ];

    /**
     * The words that pick an icon, most specific first. A word matches at the start of a word
     * ("tea" finds "Teas" and "Tea time", not "Steak"); Ethiopic and Arabic script the same.
     */
    private const WORDS = [
        'jebena' => ['ceremony', 'jebena', 'jabana', 'ጀበና', 'buna ceremony', 'ceremonia'],
        'cup' => ['coffee', 'buna', 'ቡና', 'qaxwo', 'kafee', 'kaffee', 'café', 'cafe', 'espresso', 'macchiato', 'latte', 'cappuccino', 'mocha', 'قهوة', 'hot drink'],
        'shrimp' => ['prawn', 'shrimp', 'lobster', 'crab', 'calamari', 'squid', 'octopus', 'shellfish', 'ሽሪምፕ', 'جمبري', 'روبيان', 'aargoosto'],
        'fish' => ['fish', 'seafood', 'sea food', 'catch', 'ዓሳ', 'አሳ', 'kalluun', 'سمك', 'mallay', 'tuna', 'kingfish', 'snapper', 'tilapia'],
        'skewer' => ['skewer', 'kebab', 'kabab', 'kebob', 'nyama', 'choma', 'mishkaki', 'shish', 'brochette', 'kawarma', 'shashlik'],
        'flame' => ['grill', 'tibs', 'ጥብስ', 'bbq', 'barbecue', 'barbeque', 'meat', 'ስጋ', 'hilib', 'lahm', 'لحم', 'مشاوي', 'steak', 'kitfo', 'ክትፎ', 'beef', 'lamb', 'goat', 'roast', 'burger', 'wings'],
        'pizza' => ['pizza', 'ፒዛ', 'بيتزا'],
        'egg' => ['breakfast', 'ቁርስ', 'quraac', 'brunch', 'egg', 'فطور', 'morning', 'chechebsa', 'ffit', 'fetira', 'ፍትፍት', 'ጨጨብሳ'],
        'bread' => ['bread', 'bakery', 'ዳቦ', 'rooti', 'canjeero', 'sandwich', 'toast', 'خبز', 'wrap', 'shawarma', 'sambusa', 'sambuusa', 'ሳምቡሳ'],
        'cake' => ['dessert', 'sweet', 'cake', 'ኬክ', 'pastr', 'pastry', 'macmacaan', 'حلو', 'ice cream', 'gelato', 'waffle', 'pancake', 'crepe', 'baklava', 'halwo', 'xalwo'],
        'leaf' => ['tea', 'shai', 'ሻይ', 'shaah', 'شاي', 'vegan', 'vegetarian', 'fasting', 'tsom', 'ፆም', 'ጾም', 'herbal', 'green', 'plant'],
        'glass' => ['juice', 'ጭማቂ', 'cabitaan', 'smoothie', 'shake', 'drink', 'መጠጥ', 'beverage', 'soda', 'soft', 'cold', 'عصير', 'مشروب', 'mocktail', 'lemonade', 'spris', 'sharbat', 'casiir'],
        'bowl' => ['salad', 'ሰላጣ', 'saalad', 'bowl', 'soup', 'shorba', 'maraq', 'سلطة', 'شوربة', 'starter', 'appetizer', 'mezze', 'meze', 'side'],
        'rice' => ['rice', 'ሩዝ', 'bariis', 'أرز', 'رز', 'biryani', 'pilau', 'pilaf', 'mandi', 'kabsa', 'pasta', 'noodle', 'ፓስታ', 'baasto'],
        'wine' => ['wine', 'ወይን', 'tej', 'ጠጅ', 'cocktail', 'beer', 'ቢራ', 'spirits', 'champagne', 'bar'],
        'star' => ['special', 'chef', 'signature', 'popular', 'recommend', 'today', 'house', 'favourite', 'favorite'],
        'cloche' => ['main', 'ዋና', 'dinner', 'lunch', 'platter', 'plates', 'entrée', 'entree', 'course', 'menu', 'ምሳ', 'ራት', 'qado', 'casho'],
    ];

    /** Per style: the icons in turn for names without a known word. */
    public const SETS = [
        'coffee' => ['cup', 'cake', 'bread', 'leaf', 'glass', 'egg'],
        'grill' => ['flame', 'skewer', 'cloche', 'bowl', 'glass', 'plate'],
        'garden' => ['leaf', 'bowl', 'glass', 'egg', 'cake', 'plate'],
        'coastal' => ['fish', 'shrimp', 'rice', 'bowl', 'glass', 'plate'],
        'luxe' => ['star', 'cloche', 'bowl', 'wine', 'cake', 'plate'],
        'burger' => ['burger', 'pizza', 'fries', 'drumstick', 'glass', 'cake'],
        'injera' => ['injera', 'pot', 'flame', 'leaf', 'cup', 'glass'],
        'juice' => ['tallglass', 'citrus', 'glass', 'bowl', 'leaf', 'cake'],
    ];

    /**
     * The icon for a category.
     *
     * @param  list<string>  $names  the category's name in each language
     * @param  int  $index  its place on the page (0-based), for the fallback turn
     * @param  string|null  $chosen  the icon set on the category (API field "icon"), if it is one of ours
     */
    public static function pick(string $style, array $names, int $index, ?string $chosen = null): string
    {
        if ($chosen !== null && isset(self::PATHS[$chosen])) {
            return $chosen;
        }
        $text = mb_strtolower(implode(' · ', $names));
        foreach ([...(self::STYLE_WORDS[$style] ?? []), ...self::WORDS] as $icon => $words) {
            foreach ($words as $word) {
                if (preg_match('/(?<![\p{L}\p{M}])'.preg_quote(mb_strtolower($word), '/').'/u', $text) === 1) {
                    return $icon;
                }
            }
        }
        $set = self::SETS[$style] ?? self::SETS['coffee'];

        return $set[$index % count($set)];
    }

    /**
     * The <symbol>s of the given icons, for one hidden <svg> at the top of the page.
     *
     * @param  list<string>  $icons
     */
    public static function symbols(array $icons): string
    {
        $out = '';
        foreach (array_values(array_unique($icons)) as $icon) {
            if (isset(self::PATHS[$icon])) {
                $out .= '<symbol id="i-'.$icon.'" viewBox="0 0 24 24"><path d="'.self::PATHS[$icon].'"/></symbol>';
            }
        }

        return $out;
    }
}
