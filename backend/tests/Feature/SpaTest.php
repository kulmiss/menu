<?php

use App\Enums\Role;
use Illuminate\Support\Facades\File;

/** A stand-in for the built admin app (frontend/ → public/app). */
function withBuiltSpa(callable $test): void
{
    $index = public_path('app/index.html');
    $existed = is_file($index);
    $original = $existed ? File::get($index) : null;
    File::ensureDirectoryExists(dirname($index));
    File::put($index, '<!doctype html><html><head><title>Menu</title></head><body><div id="root"></div></body></html>');
    try {
        $test();
    } finally {
        $existed ? File::put($index, $original) : File::delete($index);
    }
}

it('serves the admin app to restaurant teams at /admin, deep links included', function (string $url) {
    [$owner] = member(Role::Owner);
    withBuiltSpa(fn () => $this->actingAs($owner)->get($url)
        ->assertOk()
        ->assertSee('<div id="root"></div>', false)
        ->assertDontSee('super-path')
        ->assertDontSee('/super', false)
        ->assertHeader('X-Robots-Tag', 'noindex'));
})->with(['/admin', '/admin/team', '/admin/menu/3']);

it('serves the Super Admin app at its own address, which only its page names', function () {
    withBuiltSpa(fn () => $this->actingAs(superAdmin())->get('/super/restaurants/5')
        ->assertOk()
        ->assertSee('<meta name="super-path" content="/super"></head>', false)
        ->assertHeader('X-Robots-Tag', 'noindex'));
});

it('does not catch other paths, nor the old addresses', function (string $url) {
    withBuiltSpa(fn () => $this->actingAs(superAdmin())->get($url)->assertNotFound());
})->with(['/somewhere-else/admin', '/platform', '/platform/restaurants', '/auth/sign-in', '/auth/two-factor', '/auth/sign-up', '/auth/forgot-password', '/auth/reset-password', '/auth/invite/abc', '/auth/google/finish']);
