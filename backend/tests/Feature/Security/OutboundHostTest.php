<?php

use App\Support\Backups\S3;
use App\Support\MailSettings;
use App\Support\Security\OutboundHost;
use Illuminate\Support\Facades\Http;

/** SECURITY_REPORT.md M7: the SMTP host and S3 endpoint can't reach private networks. */
it('sorts addresses into public, loopback and private', function (string $host, bool $loopbackOk, ?string $problem) {
    expect(OutboundHost::problem($host, $loopbackOk))->toBe($problem);
})->with([
    ['8.8.8.8', false, null],
    ['2606:4700:4700::1111', false, null],
    ['127.0.0.1', false, 'loopback'],
    ['127.0.0.1', true, null],
    ['localhost', true, null],
    ['localhost', false, 'loopback'],
    ['::1', false, 'loopback'],
    ['10.0.0.5', true, 'private'],
    ['192.168.1.10', true, 'private'],
    ['172.16.3.4', true, 'private'],
    ['169.254.169.254', true, 'private'],
    ['100.64.0.1', true, 'private'],
    ['0.0.0.0', true, 'private'],
    ['::ffff:10.0.0.1', true, 'private'],
    ['fd00::1', true, 'private'],
    ['fe80::1', true, 'private'],
    ['not-a-real-host.invalid', false, null],
]);

it('refuses a private SMTP host or a non-mail port, but keeps "localhost" for cPanel mail', function () {
    $admin = superAdmin();
    $values = fn (array $o) => ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'password' => 'p', 'from_address' => 'menu@example.com', 'from_name' => 'Menu', ...$o];

    $this->actingAs($admin)->putJson(p('settings/mail'), $values(['host' => '169.254.169.254']), idem())->assertInvalid(['host' => 'private network']);
    $this->putJson(p('settings/mail'), $values(['host' => '10.0.0.8']), idem())->assertInvalid('host');
    $this->putJson(p('settings/mail'), $values(['port' => 6379]), idem())->assertInvalid(['port' => '587']);
    $this->putJson(p('settings/mail'), $values(['port' => 22]), idem())->assertInvalid('port');
    $this->putJson(p('settings/mail'), $values(['host' => 'localhost', 'port' => 25, 'encryption' => 'none']), idem())->assertOk();

    // Changed behind the form's back (or by DNS): the test e-mail checks again.
    MailSettings::save($values(['host' => '192.168.0.2']), 'p');
    $this->postJson(p('settings/mail/test'), ['to' => 'x@example.com'], idem())->assertInvalid('host');
    MailSettings::save($values(['host' => 'localhost', 'port' => 3306]), 'p');
    $this->postJson(p('settings/mail/test'), ['to' => 'x@example.com'], idem())->assertInvalid('port');
});

it('allows only a public https S3 endpoint, checked again before each upload', function () {
    $admin = superAdmin();
    $base = ['backup_s3_region' => 'auto', 'backup_s3_bucket' => 'b', 'backup_s3_key' => 'k', 'backup_s3_secret' => 's'];

    $this->actingAs($admin)->putJson(p('backups/settings'), ['backup_s3_endpoint' => 'http://s3.example.com', ...$base], idem())->assertInvalid('backup_s3_endpoint');
    $this->putJson(p('backups/settings'), ['backup_s3_endpoint' => 'https://127.0.0.1:9000', ...$base], idem())->assertInvalid('backup_s3_endpoint');
    $this->putJson(p('backups/settings'), ['backup_s3_endpoint' => 'https://10.1.2.3', ...$base], idem())->assertInvalid('backup_s3_endpoint');
    $this->putJson(p('backups/settings'), ['backup_s3_endpoint' => 'https://169.254.169.254', ...$base], idem())->assertInvalid('backup_s3_endpoint');

    Http::fake();
    $file = tempnam(sys_get_temp_dir(), 'bk');
    file_put_contents($file, 'x');
    expect(fn () => (new S3('http://8.8.8.8', 'auto', 'b', 'k', 's'))->put($file, 'o'))->toThrow(RuntimeException::class, 'public address')
        ->and(fn () => (new S3('https://192.168.1.1', 'auto', 'b', 'k', 's'))->put($file, 'o'))->toThrow(RuntimeException::class)
        ->and(fn () => (new S3('https://127.0.0.1', 'auto', 'b', 'k', 's'))->delete('o'))->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});
