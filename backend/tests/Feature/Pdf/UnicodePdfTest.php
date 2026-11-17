<?php

use App\Models\Item;
use App\Models\Tenant;
use App\Support\Pdf\Script;
use App\Support\PublicMenu\MenuPageData;
use App\Support\PublicMenu\PdfMenu;
use App\Support\Qr\TableCards;

/**
 * PDFs in any language (owner, 2026-11-17, QUESTIONS #205). Bug: Amharic,
 * Arabic and other non-Western text came out as "?" and emoji as "?".
 */
function unicodeMenuPdf(Tenant $tenant): string
{
    return asTenant($tenant, fn () => (new PdfMenu)->render(app(MenuPageData::class)->build($tenant), ['compress' => false]));
}

/** Text drawn with MenuPdfSans is stored as UTF-16 in the PDF (in drawing order, escaped like any PDF string). */
function drawnUnicode(string $pdf, string $text): bool
{
    $utf16 = (string) mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');

    return str_contains($pdf, str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $utf16));
}

it('keeps English menus on the built-in fonts, exactly as before, with emoji left out cleanly', function () {
    $tenant = Tenant::factory()->create(['name' => 'Buna & Bread']);
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, fn () => Item::create(['category_id' => $category->id, 'name' => ['en' => 'Pizza 🍕 Margherita'], 'summary' => ['en' => 'Hot 🔥 and fresh'], 'price_minor' => 30000, 'sort' => 2]));

    $pdf = unicodeMenuPdf($tenant);

    expect($pdf)->toContain('(Buna & Bread)')->toContain('(Pizza Margherita)')->toContain('(Hot and fresh)')
        ->not->toContain('MenuPdfSans')->not->toContain('?');
});

it('prints Amharic, mixed Amharic and English, Cyrillic and Greek with the bundled font', function () {
    $tenant = Tenant::factory()->create(['name' => 'ጣዕም ካፌ']);
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        $category->update(['name' => ['en' => 'ምግቦች'], 'description' => ['en' => 'የኢትዮጵያ ባህላዊ ምግቦች ከእንጀራ ጋር።']]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'ዶሮ ወጥ'], 'summary' => ['en' => 'በበርበሬ የተሰራ'], 'price_minor' => 45050, 'sort' => 2]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Doro wat ዶሮ ወጥ 🍗'], 'price_minor' => 42000, 'sort' => 3]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Борщ'], 'summary' => ['en' => 'Σαλάτα'], 'price_minor' => 5000, 'sort' => 4]);
    });

    $pdf = unicodeMenuPdf($tenant);

    // The old output: question marks in place of the letters, like "(?? ??)".
    expect($pdf)->toContain('MenuPdfSans')->not->toMatch('/\(\?[? ]*\)/');
    foreach (['ጣዕም ካፌ', 'ምግቦች', 'የኢትዮጵያ ባህላዊ ምግቦች ከእንጀራ ጋር።', 'ዶሮ ወጥ', 'በበርበሬ የተሰራ', 'Doro wat ዶሮ ወጥ', 'Борщ', 'Σαλάτα'] as $text) {
        expect(drawnUnicode($pdf, $text))->toBeTrue($text);
    }
    // The page footer too (it is centred: measured and drawn in the same font).
    expect(drawnUnicode($pdf, 'ጣዕም ካፌ   ·   1 / 1'))->toBeTrue('footer');
    // English in the same menu stays on the built-in font.
    expect($pdf)->toContain('(Macchiato)')->toContain('(Br 120)');
});

it('prints Arabic joined and right to left, and Hebrew right to left', function () {
    $tenant = Tenant::factory()->create(['name' => 'Saffron']);
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'شاي بالنعناع'], 'summary' => ['en' => 'لحم مشوي 2 قطع'], 'price_minor' => 3500, 'sort' => 2]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'שקשוקה'], 'price_minor' => 6000, 'sort' => 3]);
    });

    $pdf = unicodeMenuPdf($tenant);

    expect(drawnUnicode($pdf, Script::visual(Script::shape('شاي بالنعناع'))))->toBeTrue()
        ->and(drawnUnicode($pdf, Script::visual(Script::shape('لحم مشوي 2 قطع'))))->toBeTrue()
        ->and(drawnUnicode($pdf, 'הקושקש'))->toBeTrue()
        // Joined forms only: the font has no plain Arabic letters.
        ->and(drawnUnicode($pdf, 'شاي'))->toBeFalse();
});

it('leaves out emoji-only names and letters no bundled font has, without breaking the page', function () {
    $tenant = Tenant::factory()->create(['name' => 'Mixed']);
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        Item::create(['category_id' => $category->id, 'name' => ['en' => '🍕🍕🍕'], 'price_minor' => 1000, 'sort' => 2]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => '麻婆豆腐 Mapo tofu'], 'price_minor' => 9000, 'sort' => 3]);
    });

    $pdf = unicodeMenuPdf($tenant);

    expect($pdf)->toStartWith('%PDF')->toContain('(Mapo tofu)')->toContain('(Br 10)')->not->toMatch('/\(\?[? ]*\)/');
});

it('makes a very long Amharic menu quickly, embedding only the letters used', function () {
    $tenant = Tenant::factory()->create(['name' => 'ትልቅ ምናሌ']);
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        for ($i = 0; $i < 600; $i++) {
            Item::create(['category_id' => $category->id, 'name' => ['en' => "ዶሮ ወጥ ቁጥር {$i} — Doro wat"], 'summary' => ['en' => str_repeat('በበርበሬ የተሰራ የዶሮ ወጥ ከእንቁላል ጋር። ', 3)], 'price_minor' => 45050 + $i, 'sort' => $i + 2]);
        }
    });

    $start = microtime(true);
    $pdf = asTenant($tenant, fn () => (new PdfMenu)->render(app(MenuPageData::class)->build($tenant)));
    $seconds = microtime(true) - $start;

    expect($pdf)->toStartWith('%PDF')
        ->and(strlen($pdf))->toBeLessThan(600_000) // two subset fonts, not the whole 420 KB of font files twice
        ->and($seconds)->toBeLessThan(15);
});

it('prints table cards with an Amharic restaurant name', function () {
    $pdf = TableCards::pdf([['title' => 'ጣዕም ካፌ 🍽️', 'subtitle' => 'ጠረጴዛ 4', 'label' => 'Table 4', 'url' => 'https://example.com/q/ABCD1234']], '#9a3412');

    expect($pdf)->toStartWith('%PDF')->toContain('MenuPdfSans');
});
