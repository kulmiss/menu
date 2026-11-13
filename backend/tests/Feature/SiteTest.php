<?php

use App\Support\PlatformSettings;

it('shows the home page with links to sign up and sign in', function () {
    PlatformSettings::save(['name' => 'Buna Menus']);

    $this->get('/')->assertOk()
        ->assertSee('Buna Menus')
        ->assertSee('href="/auth/sign-up"', false)
        ->assertSee('href="/auth/sign-in"', false)
        ->assertSee('href="/terms"', false);
});

it('hides "Create a free account" when sign-up is closed', function () {
    PlatformSettings::save(['signup_open' => false]);

    $this->get('/')->assertOk()
        ->assertDontSee('href="/auth/sign-up"', false)
        ->assertSee('href="/auth/sign-in"', false);
});

it('shows the Terms and Privacy texts from platform settings, escaped', function () {
    PlatformSettings::save([
        'terms' => "1. Use the menu fairly.\nNo spam.\n\n2. <script>alert(1)</script> is shown as text.",
        'privacy' => '',
    ]);

    $this->get('/terms')->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('1. Use the menu fairly.<br />', false)
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
    $this->get('/privacy')->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('has not been published yet');
});
