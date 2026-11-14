<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

/** SECURITY_REPORT.md M11 (security events) and L14 (no secrets or personal data in them). */
beforeEach(function () {
    $this->log = sys_get_temp_dir().'/security-'.uniqid().'.log';
    config(['logging.channels.security' => ['driver' => 'single', 'path' => $this->log, 'level' => 'info']]);
    app('log')->forgetChannel('security');
    Notification::fake();
});
afterEach(fn () => @unlink($this->log));

function securityLines(): array
{
    return array_values(array_filter(explode("\n", (string) @file_get_contents(test()->log))));
}

it('records sign-in failures, lockouts, resets and password changes without personal data', function () {
    $user = User::factory()->create(['email' => 'selam@example.com', 'password' => 'correct-horse-battery']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->postJson('/api/v1/auth/login', ['email' => 'selam@example.com', 'password' => 'wrong-guess-123'], idem())->assertUnprocessable();
    $this->postJson('/api/v1/mobile/auth/login', ['email' => 'nobody@example.com', 'password' => 'another-guess', 'device_name' => 'X', 'platform' => 'ios'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'selam@example.com'], idem())->assertOk();
    $this->actingAs($user)->putJson('/api/v1/auth/user/password', [
        'current_password' => 'correct-horse-battery', 'password' => 'brand-new-secret-9', 'password_confirmation' => 'brand-new-secret-9',
    ], idem())->assertOk();

    $text = implode("\n", securityLines());
    foreach (['login.failed', 'password.reset_requested', 'password.changed', 'tokens.revoked'] as $event) {
        expect($text)->toContain('INFO: '.$event.' ');
    }
    expect($text)->toContain('"user":'.$user->id)->toContain('"known":false')
        // L14: none of the secrets or personal data appear.
        ->not->toContain('selam@example.com')->not->toContain('nobody@example.com')
        ->not->toContain('198.51.100.7')->not->toContain('wrong-guess-123')->not->toContain('another-guess')
        ->not->toContain('correct-horse-battery')->not->toContain('brand-new-secret-9');
});

it('records the per-account lock and failed two-factor codes', function () {
    $user = User::factory()->create(['email' => 'twofa@example.com', 'password' => 'correct-horse-battery', 'two_factor_secret' => encrypt((new Google2FA)->generateSecretKey()), 'two_factor_confirmed_at' => now()]);
    foreach (range(1, 31) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.5.0.$i"])->postJson('/api/v1/auth/login', ['email' => 'twofa@example.com', 'password' => "guess-$i"], idem());
    }
    expect(implode("\n", securityLines()))->toContain('login.locked');

    $this->travel(2)->hours();
    $this->postJson('/api/v1/auth/login', ['email' => 'twofa@example.com', 'password' => 'correct-horse-battery'], idem())->assertOk();
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'], idem())->assertUnprocessable();
    expect(implode("\n", securityLines()))->toContain('two_factor.failed')->toContain('"user":'.$user->id)->not->toContain('000000');
});

it('records refused uploads and private outbound hosts', function () {
    [$owner, $tenant] = member(Role::Owner);
    $this->actingAs($owner)->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->create('x.svg', 3, 'image/svg+xml')], ['Accept' => 'application/json'] + idem())->assertUnprocessable();
    $this->actingAs(superAdmin())->putJson(p('settings/mail'), ['host' => '10.0.0.9', 'port' => 587, 'encryption' => 'tls'], idem())->assertInvalid('host');

    $text = implode("\n", securityLines());
    expect($text)->toContain('upload.refused')->toContain('"why":"type"')->toContain('outbound.refused')->toContain('"why":"private"')
        ->not->toContain('10.0.0.9');
});
