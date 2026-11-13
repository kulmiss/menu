<?php

use App\Support\Legal\LegalText;
use App\Support\PlatformSettings;

it('gives the app its start-up settings without signing in', function () {
    PlatformSettings::save(['upgrade_whatsapp' => '+251 911 000000', 'support_email' => 'help@example.com']);

    $this->getJson('/api/v1/mobile/config')->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertJsonPath('data.min_version', '1.0.0')
        ->assertJsonPath('data.links.support_whatsapp', 'https://wa.me/251911000000')
        ->assertJsonPath('data.links.support_email', 'help@example.com')
        ->assertJsonPath('data.links.privacy', url('/privacy'));
});

it('lets the Super Admin set the minimum app version and flags', function () {
    $this->actingAs(superAdmin())->putJson(p('settings'), ['app_min_version' => '1.2.0', 'app_flags' => ['insights_v2' => true]], idem())->assertOk();
    $this->putJson(p('settings'), ['app_min_version' => 'one'], idem())->assertUnprocessable();

    $this->getJson('/api/v1/mobile/config')->assertJsonPath('data.min_version', '1.2.0')->assertJsonPath('data.flags.insights_v2', true);
});

it('mentions the app in the default Privacy Policy and updates only an unedited stored text', function () {
    expect(LegalText::default('privacy'))->toContain('Menu Admin app')->toContain('Firebase');
    $migration = require database_path('migrations/2026_11_15_100004_update_privacy_default_mobile_app.php');

    PlatformSettings::save(['privacy' => file_get_contents(database_path('legal-history/privacy-2026-11-14-v2.md'))]);
    $migration->up();
    expect(PlatformSettings::get('privacy'))->toBe(LegalText::default('privacy'));

    PlatformSettings::save(['privacy' => 'Our own text.']);
    $migration->up();
    expect(PlatformSettings::get('privacy'))->toBe('Our own text.');
});
