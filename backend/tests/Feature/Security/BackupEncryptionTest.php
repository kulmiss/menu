<?php

use App\Enums\Role;
use App\Models\Backup;
use App\Support\Backups\BackupCrypto;
use App\Support\Backups\Backups;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/** SECURITY_REPORT.md H4: backups encrypted at rest with a key outside the backup. */
beforeEach(function () {
    File::deleteDirectory(Backup::dir());
    Notification::fake();
    $this->tmp = sys_get_temp_dir().'/bk-'.uniqid();
    File::ensureDirectoryExists($this->tmp);
});
afterEach(function () {
    File::deleteDirectory(Backup::dir());
    File::deleteDirectory($this->tmp);
});

it('round-trips files of any size and detects a wrong key, tampering and truncation', function () {
    $key = base64_decode(substr(BackupCrypto::newKey(), 7));
    foreach ([0, 10, 1048576, 2621440 + 7] as $size) {
        $plain = $size > 0 ? random_bytes($size) : '';
        File::put($this->tmp.'/in', $plain);
        BackupCrypto::encryptFile($this->tmp.'/in', $this->tmp.'/in.enc', $key);
        $cipher = (string) file_get_contents($this->tmp.'/in.enc');
        expect($cipher)->toStartWith(BackupCrypto::MAGIC)
            ->and($size === 0 || ! str_contains($cipher, substr($plain, 0, 64)))->toBeTrue();
        BackupCrypto::decryptFile($this->tmp.'/in.enc', $this->tmp.'/out', $key);
        expect(hash_file('sha256', $this->tmp.'/out'))->toBe(hash('sha256', $plain));
    }

    $wrong = base64_decode(substr(BackupCrypto::newKey(), 7));
    expect(fn () => BackupCrypto::decryptFile($this->tmp.'/in.enc', $this->tmp.'/x', $wrong))->toThrow(RuntimeException::class, 'Wrong BACKUP_KEY');
    expect(is_file($this->tmp.'/x'))->toBeFalse();

    $bytes = (string) file_get_contents($this->tmp.'/in.enc');
    File::put($this->tmp.'/bad.enc', substr_replace($bytes, chr(ord($bytes[100]) ^ 1), 100, 1));
    expect(fn () => BackupCrypto::decryptFile($this->tmp.'/bad.enc', $this->tmp.'/x', $key))->toThrow(RuntimeException::class);
    File::put($this->tmp.'/cut.enc', substr($bytes, 0, (int) (strlen($bytes) * 0.6)));
    expect(fn () => BackupCrypto::decryptFile($this->tmp.'/cut.enc', $this->tmp.'/x', $key))->toThrow(RuntimeException::class);
});

it('stores only the encrypted file, and the decrypt command gives back the original zip', function () {
    [, $tenant] = member(Role::Owner);
    config(['backups.key' => $key = BackupCrypto::newKey()]);

    $backup = app(Backups::class)->run();

    expect($backup->status)->toBe('ok')->and($backup->filename)->toEndWith('.zip.enc')->and($backup->encrypted())->toBeTrue()
        ->and(glob(Backup::dir().'/*.zip'))->toBe([])
        ->and((string) file_get_contents((string) $backup->path()))->not->toContain($tenant->slug);

    config(['backups.key' => null]);
    $this->artisan('backup:decrypt', ['file' => $backup->path(), 'out' => $this->tmp.'/r.zip'])->assertFailed();
    $this->artisan('backup:decrypt', ['file' => $backup->path(), 'out' => $this->tmp.'/r.zip', '--key' => $key])->assertSuccessful();
    $zip = new ZipArchive;
    expect($zip->open($this->tmp.'/r.zip'))->toBeTrue()
        ->and((string) $zip->getFromName('database.sql'))->toContain($tenant->slug);
    $zip->close();
});

it('never copies a readable backup off-site, and fails loudly on a broken key', function () {
    PlatformSettings::save([
        'backup_s3_enabled' => true, 'backup_s3_endpoint' => 'https://s3.example.com', 'backup_s3_bucket' => 'b',
        'backup_s3_key' => 'AK', 'backup_s3_secret' => Crypt::encryptString('s'),
    ]);
    Http::fake();
    config(['backups.key' => null]);

    $plain = app(Backups::class)->run();
    expect($plain->status)->toBe('ok')->and($plain->encrypted())->toBeFalse()
        ->and($plain->offsite)->toBe('failed')->and($plain->error)->toContain('BACKUP_KEY');
    Http::assertNothingSent();

    config(['backups.key' => 'base64:'.base64_encode('too short')]);
    $broken = app(Backups::class)->run();
    expect($broken->status)->toBe('failed')->and($broken->error)->toContain('BACKUP_KEY');

    $this->actingAs(superAdmin())->getJson(p('backups'))->assertOk()->assertJsonPath('data.encryption', 'invalid');
    config(['backups.key' => null]);
    $this->getJson(p('backups'))->assertJsonPath('data.encryption', 'off')->assertJsonPath('data.backups.1.encrypted', false);
});

it('makes a key and adds it to .env only when there is none', function () {
    $env = base_path('.env');
    $saved = is_file($env) ? file_get_contents($env) : null;
    try {
        file_put_contents($env, "APP_NAME=Menu\nBACKUP_KEY=\n");
        $this->artisan('backup:key --write')->assertSuccessful();
        preg_match('/^BACKUP_KEY=(base64:\S+)$/m', (string) file_get_contents($env), $m);
        config(['backups.key' => $m[1] ?? '']);
        expect(BackupCrypto::enabled())->toBeTrue()->and(substr_count((string) file_get_contents($env), 'BACKUP_KEY='))->toBe(1);

        $this->artisan('backup:key --write')->assertFailed();
        expect((string) file_get_contents($env))->toContain($m[1]);
    } finally {
        $saved === null ? @unlink($env) : file_put_contents($env, $saved);
    }
});
