<?php

use App\Support\Contrast;

it('computes WCAG contrast ratios', function () {
    expect(round(Contrast::ratio('#000000', '#ffffff'), 1))->toBe(21.0)
        ->and(round(Contrast::ratio('#ffffff', '#ffffff'), 1))->toBe(1.0)
        ->and(round(Contrast::ratio('#767676', '#ffffff'), 2))->toBe(4.54);
});

it('picks white or near-black text, whichever reads better', function () {
    expect(Contrast::textOn('#1d4ed8'))->toBe('#ffffff')
        ->and(Contrast::textOn('#fde047'))->toBe(Contrast::DARK_BG);
});

it('keeps readable colours and nudges unreadable ones until they reach 4.5:1', function (string $colour, string $bg) {
    $fixed = Contrast::readableOn($colour, $bg);

    expect(Contrast::ratio($fixed, $bg))->toBeGreaterThanOrEqual(Contrast::MIN);
    if (Contrast::ratio($colour, $bg) >= Contrast::MIN) {
        expect($fixed)->toBe($colour);
    }
})->with([
    ['#b4432a', Contrast::LIGHT_BG],
    ['#fde047', Contrast::LIGHT_BG],  // yellow on white → darkened
    ['#ffffff', Contrast::LIGHT_BG],
    ['#1d4ed8', Contrast::DARK_BG],   // blue in dark mode → lightened
    ['#000000', Contrast::DARK_BG],
]);

it('warns about colours that need adjusting', function () {
    expect(Contrast::warnings('#fde047'))->toBe(['appearance.contrast.light'])
        ->and(Contrast::warnings('#1e1b4b'))->toBe([])  // dark mode adjusts by itself
        ->and(Contrast::warnings('#9a6b00'))->toBe([]);
});
