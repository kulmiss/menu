<?php

use App\Models\Plan;
use App\Notifications\SignupNotification;
use App\Support\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Platform settings → Sign-up and the e-mail logo (owner, 2026-11-13). */
it('saves the sign-up settings and keeps the Turnstile secret encrypted and hidden', function () {
    $admin = superAdmin();
    $free = Plan::where('slug', 'free')->sole();

    $this->actingAs($admin)->getJson(p('settings'))->assertOk()
        ->assertJsonPath('data.signup_open', true)->assertJsonPath('data.signup_approval', false)
        ->assertJsonPath('data.signup_notify', true)->assertJsonPath('data.turnstile_secret_set', false)
        ->assertJsonMissingPath('data.turnstile_secret')
        ->assertJsonPath('data.plans.0.slug', 'free');

    $this->actingAs($admin)->putJson(p('settings'), [
        'signup_open' => false, 'signup_approval' => true, 'signup_notify' => false, 'signup_plan_id' => $free->id,
        'turnstile_enabled' => true, 'turnstile_site_key' => '0x4AAA', 'turnstile_secret' => '0x4BBB-secret',
        'disposable_domains' => ['Spam.Example', 'spam.example', 'junk.test'], 'upgrade_whatsapp' => '+251 91 555 0100',
    ], idem())->assertOk()
        ->assertJsonPath('data.turnstile_secret_set', true)->assertJsonMissingPath('data.turnstile_secret')
        ->assertJsonPath('data.disposable_domains', ['spam.example', 'junk.test']);

    $stored = json_decode((string) DB::table('platform_settings')->where('key', 'turnstile_secret')->value('value'), true);
    expect($stored)->not->toContain('0x4BBB')->and(PlatformSettings::turnstileSecret())->toBe('0x4BBB-secret');

    // Saving without a secret keeps it; clearing removes it (and Turnstile needs it to stay on).
    $this->actingAs($admin)->putJson(p('settings'), ['turnstile_site_key' => '0x4AAA'], idem())->assertOk();
    expect(PlatformSettings::turnstileSecret())->toBe('0x4BBB-secret');
    $this->actingAs($admin)->putJson(p('settings'), ['turnstile_enabled' => false, 'clear_turnstile_secret' => true], idem())->assertOk()->assertJsonPath('data.turnstile_secret_set', false);
    $this->actingAs($admin)->putJson(p('settings'), ['turnstile_enabled' => true], idem())->assertJsonValidationErrors('turnstile_enabled');
    $this->actingAs($admin)->putJson(p('settings'), ['upgrade_whatsapp' => 'call me'], idem())->assertJsonValidationErrors('upgrade_whatsapp');
});

it('puts the platform logo at the top of every e-mail', function () {
    Storage::fake('public');
    PlatformSettings::save(['name' => 'MenuHub']);
    $admin = superAdmin();
    $html = fn () => (string) (new SignupNotification('existing'))->toMail((object) ['name' => 'Selam'])->render();

    expect($html())->toContain('MenuHub');
    $this->actingAs($admin)->post(p('settings/logo'), ['image' => UploadedFile::fake()->image('logo.png', 1200, 300)], [...idem(), 'Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.logo_url', fn ($u) => str_contains($u, '/platform/logo-'));
    $path = PlatformSettings::get('logo');
    [$w, $h] = getimagesizefromstring((string) Storage::disk('public')->get($path));
    expect([$w, $h])->toBe([480, 120])->and($html())->toContain('<img src="'.Storage::disk('public')->url($path).'"');

    $this->actingAs($admin)->deleteJson(p('settings/logo'), [], idem())->assertOk()->assertJsonPath('data.logo_url', null);
    Storage::disk('public')->assertMissing($path);
});
