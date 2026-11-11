<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Support\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

function smtpValues(array $overrides = []): array
{
    return [
        'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls',
        'username' => 'menu@example.com', 'password' => 's3cret-pass',
        'from_address' => 'hello@example.com', 'from_name' => 'Menu Hub',
        ...$overrides,
    ];
}

/** The SMTP transport Laravel would use right now. */
function currentTransport(): EsmtpTransport
{
    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');
    Mail::clearResolvedInstances();
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(EsmtpTransport::class);

    return $transport;
}

it('uses the .env settings until the Super Admin saves some', function () {
    $this->actingAs(superAdmin())->getJson(p('settings/mail'))->assertOk()
        ->assertJsonPath('data.source', 'env')
        ->assertJsonPath('data.has_password', false);
});

it('saves the SMTP settings with an encrypted password that is never shown again', function () {
    $admin = superAdmin();

    $this->actingAs($admin)->putJson(p('settings/mail'), smtpValues(), idem())->assertOk()
        ->assertJsonPath('data.source', 'platform')
        ->assertJsonPath('data.host', 'smtp.example.com')
        ->assertJsonPath('data.has_password', true)
        ->assertJsonMissingPath('data.password')
        ->assertDontSee('s3cret-pass');

    $raw = (string) DB::table('platform_settings')->where('key', 'mail')->value('value');
    expect($raw)->not->toContain('s3cret-pass');
    $this->actingAs($admin)->getJson(p('settings'))->assertDontSee('s3cret-pass')->assertJsonMissingPath('data.mail');

    // Saving again without a password keeps the old one.
    $this->actingAs($admin)->putJson(p('settings/mail'), smtpValues(['password' => null, 'port' => 2525]), idem())->assertOk()
        ->assertJsonPath('data.has_password', true)->assertJsonPath('data.port', 2525);
    // …and the activity log never holds it.
    expect(ActivityLog::where('action', 'platform.mail_settings_saved')->get()->toJson())->not->toContain('s3cret-pass');

    $this->actingAs($admin)->putJson(p('settings/mail'), smtpValues(['password' => null, 'clear_password' => true]), idem())->assertOk()
        ->assertJsonPath('data.has_password', false);
});

it('sends every e-mail through the saved server', function (string $encryption, bool $tls) {
    MailSettings::save(smtpValues(['encryption' => $encryption, 'port' => $encryption === 'ssl' ? 465 : 587]), 's3cret-pass');

    $transport = currentTransport();
    $stream = $transport->getStream();
    expect($stream)->toBeInstanceOf(SocketStream::class)
        ->and($stream->getHost())->toBe('smtp.example.com')
        ->and($stream->isTLS())->toBe($tls)
        ->and($transport->getUsername())->toBe('menu@example.com')
        ->and($transport->getPassword())->toBe('s3cret-pass')
        ->and(config('mail.from.address'))->toBe('hello@example.com')
        ->and(config('mail.from.name'))->toBe('Menu Hub');
})->with([['ssl', true], ['tls', false], ['none', false]]);

it('goes back to the .env settings', function () {
    MailSettings::save(smtpValues(), 'x');

    $this->actingAs(superAdmin())->deleteJson(p('settings/mail'), [], idem())->assertOk()->assertJsonPath('data.source', 'env');
    expect(MailSettings::stored())->toBeNull();
});

it('rejects bad values', function () {
    $this->actingAs(superAdmin())->putJson(p('settings/mail'), smtpValues([
        'host' => 'smtp example', 'port' => 70000, 'encryption' => 'starttls', 'from_address' => 'nope',
    ]), idem())->assertInvalid(['host', 'port', 'encryption', 'from_address']);
});

it('sends a test e-mail and says clearly when it worked', function () {
    Mail::fake();
    $admin = superAdmin();

    $this->actingAs($admin)->postJson(p('settings/mail/test'), [], idem())->assertOk()
        ->assertJsonPath('data.sent_to', $admin->email)
        ->assertJsonPath('message', "Test e-mail sent to {$admin->email}. Check the inbox (and the spam folder).");
});

it('reports why a test e-mail failed', function () {
    // Nothing listens on this port: the connection is refused at once.
    MailSettings::save(smtpValues(['host' => '127.0.0.1', 'port' => 9, 'encryption' => 'none', 'username' => null]), null);
    currentTransport();

    $this->actingAs(superAdmin())->postJson(p('settings/mail/test'), ['to' => 'someone@example.com'], idem())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'mail.failed')
        ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_starts_with($m, 'The test e-mail could not be sent: ') && strlen($m) > 40)->etc());
});

it('is hidden from everyone but Super Admins', function () {
    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->getJson(p('settings/mail'))->assertNotFound();
    $this->actingAs($owner)->postJson(p('settings/mail/test'), [], idem())->assertNotFound();
});
