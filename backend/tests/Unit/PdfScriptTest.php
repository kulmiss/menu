<?php

use App\Support\Pdf\Script;

/** Text made ready for the PDF fonts (QUESTIONS #205). */
$all = fn (int $cp) => true;
$latinOnly = fn (int $cp) => $cp < 0x250;

it('leaves emoji out cleanly: no "?" and no double spaces', function () use ($latinOnly) {
    expect(Script::clean('Pizza 🍕 Margherita', $latinOnly))->toBe('Pizza Margherita')
        ->and(Script::clean('🔥Spicy wings🔥', $latinOnly))->toBe('Spicy wings')
        ->and(Script::clean('Coffee ☕️ & cake 👨‍👩‍👧', $latinOnly))->toBe('Coffee & cake')
        ->and(Script::clean('🍕🍕🍕', $latinOnly))->toBe('')
        ->and(Script::clean("Line one 🍕\nLine two", $latinOnly))->toBe("Line one\nLine two")
        // Spacing that was typed stays (the menu header uses "Bole   ·   Bole Road").
        ->and(Script::clean('Bole   ·   Bole Road', $latinOnly))->toBe('Bole   ·   Bole Road');
});

it('leaves out letters the fonts do not have, keeps the rest', function () use ($latinOnly) {
    expect(Script::clean('麻婆豆腐 Mapo tofu', $latinOnly))->toBe('Mapo tofu');
});

it('keeps letters the font has, and drops Arabic vowel marks and Hebrew points', function () use ($all) {
    expect(Script::clean('ዶሮ ወጥ', $all))->toBe('ዶሮ ወጥ')
        ->and(Script::clean('شَايٌ', $all))->toBe('شاي')
        ->and(Script::clean('שָׁלוֹם', $all))->toBe('שלום');
});

it('joins Arabic letters: initial, middle, final, isolated and lam-alef', function () {
    // شاي: sheen initial, alef final, yeh isolated (alef doesn't join to the next letter).
    expect(Script::shape('شاي'))->toBe("\u{FEB7}\u{FE8E}\u{FEF1}")
        // بيت: beh initial, yeh medial, teh final.
        ->and(Script::shape('بيت'))->toBe("\u{FE91}\u{FEF4}\u{FE96}")
        // لا alone and after a joining letter.
        ->and(Script::shape('لا'))->toBe("\u{FEFB}")
        ->and(Script::shape('سلام'))->toBe("\u{FEB3}\u{FEFC}\u{FEE1}")
        ->and(Script::shape('Doro wat'))->toBe('Doro wat');
});

it('draws right-to-left text from the right, numbers and Latin words keeping their order', function () {
    expect(Script::visual('Doro wat'))->toBe('Doro wat')
        ->and(Script::visual('שלום'))->toBe('םולש')
        ->and(Script::visual('קפה 120'))->toBe('120 הפק')
        ->and(Script::visual('Falafel פלאפל'))->toBe('Falafel לפאלפ')
        ->and(Script::visual('שקשוקה (חריף)'))->toBe('(ףירח) הקושקש');
});
