<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

/*
 * Landing 1 stays exactly as it was when Landing 2 was added (owner, 2026-11-22): the home
 * page, rendered with fixed data, must match the copy saved before the change byte for byte
 * (only the random script nonce and the site address are masked). To save a new copy on purpose:
 * UPDATE_GOLDEN=1 php artisan test --filter=LandingGolden
 */

function landingHtml(): string
{
    // A fixed site address: it is in the page's links and in the QR code drawn on it.
    config(['app.url' => 'http://127.0.0.1:8000']);
    URL::forceRootUrl('http://127.0.0.1:8000');
    $html = test()->get('/')->assertOk()->getContent();

    // The random script nonce and the site address (APP_URL differs per machine) are masked.
    $html = str_replace(rtrim((string) config('app.url'), '/'), 'http://APP', $html);

    return (string) preg_replace('/nonce="[^"]*"/', 'nonce="N"', $html);
}

it('renders Landing 1 exactly as before Landing 2 was added', function () {
    $this->travelTo(now()->setDate(2026, 11, 22)->setTime(12, 0));
    $golden = base_path('tests/Fixtures/landing-v1.html');
    if (getenv('UPDATE_GOLDEN') === '1') {
        File::put($golden, landingHtml());
    }
    expect(landingHtml())->toBe(File::get($golden));
});
