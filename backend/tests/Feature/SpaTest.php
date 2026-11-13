<?php

use Illuminate\Support\Facades\File;

afterEach(fn () => File::deleteDirectory(public_path('app-test-backup')));

it('serves the built SPA for admin, platform and auth deep links', function (string $url) {
    $index = public_path('app/index.html');
    $existed = is_file($index);
    $original = $existed ? File::get($index) : null;
    File::ensureDirectoryExists(dirname($index));
    File::put($index, '<!doctype html><div id="root"></div>');

    try {
        $this->get($url)
            ->assertOk()
            ->assertSee('<div id="root"></div>', false)
            ->assertHeader('X-Robots-Tag', 'noindex');
    } finally {
        $existed ? File::put($index, $original) : File::delete($index);
    }
})->with(['/admin', '/admin/team', '/platform/restaurants/5', '/auth/sign-in', '/auth/invite/abc']);

it('does not catch other paths', function () {
    $this->get('/somewhere-else/admin')->assertNotFound();
});
