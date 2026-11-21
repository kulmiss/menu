<?php

namespace App\Support\Pdf;

/**
 * Text for the PDFs: what restaurants type (any language, emoji) made ready
 * for a PDF font that has no shaping engine.
 *
 *  - clean():   drops emoji and anything the PDF fonts can't draw, cleanly
 *               (no "?" boxes, no double spaces left behind)
 *  - shape():   Arabic letters joined (initial, middle, final forms; lam-alef)
 *  - visual():  right-to-left runs (Arabic, Hebrew) put in drawing order
 *
 * PDFs only; the website shows text as typed and the browser does all this.
 */
final class Script
{
    /** Arabic letter => [isolated, final, initial, medial]; two forms = joins only to the letter before it. */
    private const FORMS = [
        0x0621 => [0xFE80],
        0x0622 => [0xFE81, 0xFE82], 0x0623 => [0xFE83, 0xFE84], 0x0624 => [0xFE85, 0xFE86], 0x0625 => [0xFE87, 0xFE88],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C], 0x0627 => [0xFE8D, 0xFE8E], 0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94], 0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98], 0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0], 0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4], 0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA], 0x0630 => [0xFEAB, 0xFEAC], 0x0631 => [0xFEAD, 0xFEAE], 0x0632 => [0xFEAF, 0xFEB0],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], 0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8], 0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0], 0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4], 0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC], 0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0], 0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8], 0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC], 0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], 0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8], 0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE], 0x0649 => [0xFEEF, 0xFEF0], 0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        // Persian and Urdu letters also used on menus.
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], 0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D], 0x0698 => [0xFB8A, 0xFB8B],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], 0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95], 0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
    ];

    /** Lam followed by these alefs => [isolated, final] ligature. */
    private const LAM_ALEF = [0x0622 => [0xFEF5, 0xFEF6], 0x0623 => [0xFEF7, 0xFEF8], 0x0625 => [0xFEF9, 0xFEFA], 0x0627 => [0xFEFB, 0xFEFC]];

    private const MIRROR = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<', '«' => '»', '»' => '«'];

    /**
     * Emoji, vowel marks the fonts can't place, invisible format characters and
     * anything $drawable says no to are removed; spaces left over are tidied.
     *
     * @param  callable(int): bool  $drawable
     */
    public static function clean(string $text, callable $drawable): string
    {
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }
        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $text);
        if (preg_match('/^[\x20-\x7E\n]*$/', $text) === 1) {
            return implode("\n", array_map(fn (string $line) => trim($line, ' '), explode("\n", $text)));
        }
        // Arabic vowel marks and Hebrew points (optional on menus; without a shaping engine they would sit wrongly).
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0591}-\x{05BD}\x{05BF}\x{05C1}\x{05C2}\x{05C4}\x{05C5}\x{05C7}]/u', '', $text);
        $out = '';
        foreach (mb_str_split($text) as $char) {
            $cp = mb_ord($char);
            if ($char === "\n" || $char === ' ' || ($cp >= 0x20 && $cp <= 0xFFFF && $drawable($cp))) {
                $out .= $char;
            } else {
                $out .= "\u{0}"; // a removed character: tidied below
            }
        }
        // "Pizza 🍕 Margherita" => "Pizza Margherita", "🔥Spicy" => "Spicy"; other spacing stays as typed.
        $out = (string) preg_replace_callback('/( ?)\x{0}+( ?)/u', fn (array $m) => $m[1].$m[2] === '' ? '' : ' ', $out);

        return implode("\n", array_map(fn (string $line) => trim($line, ' '), explode("\n", $out)));
    }

    /** Arabic letters shape() turns into forms the font has (the font has only the joined forms). */
    public static function shapes(int $cp): bool
    {
        return $cp === 0x0640 || isset(self::FORMS[$cp]);
    }

    public static function hasRtl(string $text): bool
    {
        return preg_match('/[\x{0590}-\x{05FF}\x{0600}-\x{065F}\x{066A}-\x{06EF}\x{06FA}-\x{06FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text) === 1;
    }

    /** Arabic letters in their joined forms, in reading order. */
    public static function shape(string $text): string
    {
        if (! preg_match('/[\x{0621}-\x{06FF}]/u', $text)) {
            return $text;
        }
        $cps = array_map('mb_ord', mb_str_split($text));
        $n = count($cps);
        $joinsNext = fn (?int $cp) => $cp === 0x0640 || ($cp !== null && count(self::FORMS[$cp] ?? []) === 4);
        $joins = fn (?int $cp) => $cp === 0x0640 || isset(self::FORMS[$cp ?? -1]);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $cp = $cps[$i];
            if (! isset(self::FORMS[$cp])) {
                $out[] = $cp;

                continue;
            }
            $prev = $i > 0 ? $cps[$i - 1] : null;
            $afterPrev = $joinsNext($prev);
            $next = $cps[$i + 1] ?? null;
            if ($cp === 0x0644 && isset(self::LAM_ALEF[$next ?? -1])) {
                $out[] = self::LAM_ALEF[$next][$afterPrev ? 1 : 0];
                $i++;

                continue;
            }
            $forms = self::FORMS[$cp];
            $beforeNext = count($forms) === 4 && $joins($next);
            $out[] = match (true) {
                $afterPrev && $beforeNext => $forms[3],
                $afterPrev && isset($forms[1]) => $forms[1],
                $beforeNext => $forms[2],
                default => $forms[0],
            };
        }

        return implode('', array_map('mb_chr', $out));
    }

    /**
     * One line in drawing order: right-to-left runs reversed (numbers inside
     * them keep their order); a line that starts right-to-left is laid out
     * from the right. A simplified Unicode bidi algorithm, enough for menus.
     */
    public static function visual(string $line): string
    {
        if (! self::hasRtl($line)) {
            return $line;
        }
        $type = function (string $c): string {
            $cp = mb_ord($c);

            return match (true) {
                ($cp >= 0x30 && $cp <= 0x39) || ($cp >= 0x660 && $cp <= 0x669) || ($cp >= 0x6F0 && $cp <= 0x6F9) => 'D',
                self::hasRtl($c) => 'R',
                preg_match('/\p{L}/u', $c) === 1 => 'L',
                default => 'N',
            };
        };
        $chars = mb_str_split($line);
        $types = array_map($type, $chars);
        $strong = array_values(array_filter($types, fn ($t) => $t === 'R' || $t === 'L'));
        $base = $strong[0] ?? 'L';
        // Neutrals and digits take the direction of the strong letters around them; else the line's.
        $n = count($chars);
        $dir = [];
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] === 'R' || $types[$i] === 'L') {
                $dir[$i] = $types[$i];

                continue;
            }
            $before = null;
            for ($j = $i - 1; $j >= 0; $j--) {
                if ($types[$j] === 'R' || $types[$j] === 'L') {
                    $before = $types[$j];
                    break;
                }
            }
            $after = null;
            for ($j = $i + 1; $j < $n; $j++) {
                if ($types[$j] === 'R' || $types[$j] === 'L') {
                    $after = $types[$j];
                    break;
                }
            }
            $dir[$i] = $before !== null && $before === $after ? $before : ($types[$i] === 'D' ? ($before ?? $after ?? $base) : $base);
        }
        // Runs of one direction.
        $runs = [];
        foreach ($chars as $i => $c) {
            if ($runs !== [] && $runs[count($runs) - 1][0] === $dir[$i]) {
                $runs[count($runs) - 1][1][] = [$c, $types[$i]];
            } else {
                $runs[] = [$dir[$i], [[$c, $types[$i]]]];
            }
        }
        $draw = function (array $run): string {
            [$d, $items] = $run;
            if ($d === 'L') {
                return implode('', array_column($items, 0));
            }
            // Right to left: reverse, but keep each group of digits in its own order.
            $groups = [];
            foreach ($items as [$c, $t]) {
                $digit = $t === 'D' || ($t === 'N' && in_array($c, ['.', ',', '٫', '٬'], true) && $groups !== [] && $groups[count($groups) - 1][0]);
                if ($groups !== [] && $groups[count($groups) - 1][0] === $digit && $digit) {
                    $groups[count($groups) - 1][1] .= $c;
                } else {
                    $groups[] = [$digit, $digit ? $c : (self::MIRROR[$c] ?? $c)];
                }
            }

            return implode('', array_map(fn ($g) => $g[1], array_reverse($groups)));
        };
        if ($base === 'R') {
            $runs = array_reverse($runs);
        }

        return implode('', array_map($draw, $runs));
    }
}
