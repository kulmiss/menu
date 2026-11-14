<?php

use App\Enums\Role;
use App\Jobs\RunBackup;
use App\Models\Backup;
use App\Notifications\BackupProblemNotification;
use App\Support\Backups\BackupCrypto;
use App\Support\Backups\Backups;
use App\Support\Backups\S3;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/** Super Admin → Backups (owner, 2026-11-13). */
beforeEach(function () {
    File::deleteDirectory(Backup::dir());
    File::ensureDirectoryExists(public_path('media/backup-test'));
    File::put(public_path('media/backup-test/photo.webp'), 'not really a photo');
    Notification::fake();
});
afterEach(function () {
    File::deleteDirectory(Backup::dir());
    File::deleteDirectory(public_path('media/backup-test'));
});

it('backs up the database and media into one zip outside the public folder', function () {
    [, $tenant] = member(Role::Owner);
    $this->artisan('backup:run')->assertSuccessful();

    $backup = Backup::sole();
    expect($backup->status)->toBe('ok')->and($backup->size)->toBeGreaterThan(0)
        ->and($backup->path())->toStartWith(storage_path('app/backups'))->not->toContain(public_path());
    $zip = new ZipArchive;
    $zip->open((string) $backup->path());
    $sql = (string) $zip->getFromName('database.sql');
    expect($sql)->toContain('CREATE TABLE `tenants`')->toContain($tenant->slug)
        ->and($zip->getFromName('media/backup-test/photo.webp'))->toBe('not really a photo')
        ->and(json_decode((string) $zip->getFromName('manifest.json'), true)['media']['files'])->toBeGreaterThanOrEqual(1);
    $zip->close();
    // Caches and sessions are not copied.
    expect($sql)->not->toMatch('/INSERT INTO `sessions`/');
});

it('keeps the newest backup per day, week and month and deletes the rest', function () {
    PlatformSettings::save(['backup_keep_daily' => 7, 'backup_keep_weekly' => 4, 'backup_keep_monthly' => 3]);
    File::ensureDirectoryExists(Backup::dir());
    $this->travelTo('2026-11-13 03:00:00');
    // Two a day for 120 days.
    foreach (range(0, 119) as $day) {
        foreach ([1, 13] as $hour) {
            $at = now()->subDays($day)->setTime($hour, 0);
            $name = "b-{$day}-{$hour}.zip";
            File::put(Backup::dir().'/'.$name, 'x');
            Backup::create(['filename' => $name, 'size' => 1, 'status' => 'ok', 'kind' => 'scheduled'])->forceFill(['created_at' => $at])->save();
        }
    }
    Backup::create(['status' => 'failed', 'error' => 'old'])->forceFill(['created_at' => now()->subDays(40)])->save();

    app(Backups::class)->prune();

    $left = Backup::where('status', 'ok')->orderByDesc('created_at')->get();
    $days = $left->map(fn ($b) => $b->created_at->format('Y-m-d'))->unique();
    // 7 days + the newest of 4 weeks + 3 months, overlapping: at most 14, each the newest of its day.
    expect($left->count())->toBeLessThanOrEqual(14)->toBe($days->count())
        ->and($left->first()->created_at->format('Y-m-d H'))->toBe('2026-11-13 13')
        ->and($left->pluck('created_at')->every(fn ($d) => $d->hour === 13 || $d->isSameDay(now())))->toBeTrue()
        ->and($left->last()->created_at->format('Y-m'))->toBe('2026-09')
        ->and(count(File::files(Backup::dir())))->toBe($left->count())
        ->and(Backup::where('status', 'failed')->count())->toBe(0);
});

it('warns the Super Admins once a day when a backup fails or none succeeded in 26 hours', function () {
    $admin = superAdmin();
    $this->travelTo('2026-11-13 02:30:00');
    // The zip can't be written: a folder sits where the file should go.
    $backup = Backup::create(['kind' => 'scheduled', 'status' => 'queued']);
    File::ensureDirectoryExists(Backup::dir()."/backup-20261113-023000-{$backup->id}.zip");
    $backup = app(Backups::class)->run($backup);
    expect($backup->status)->toBe('failed');
    Notification::assertSentTo($admin, BackupProblemNotification::class);
    File::deleteDirectory(Backup::dir());

    app(Backups::class)->check();
    Notification::assertSentToTimes($admin, BackupProblemNotification::class, 1); // not twice the same day

    $this->actingAs($admin)->getJson(p('backups'))->assertOk()->assertJsonPath('data.problem', fn ($p) => str_contains($p, 'failed'));
    $this->getJson(p('widgets/health'))->assertJsonPath('data.warnings', fn ($w) => in_array('backup', $w, true));

    // A good backup clears the problem; 27 hours later it comes back.
    app(Backups::class)->run();
    expect(Backups::problem())->toBeNull();
    $this->travel(27)->hours();
    expect(Backups::problem())->toContain('26 hours');
});

it('needs the password or a two-factor code again before a 5-minute download link', function () {
    $admin = superAdmin();
    $admin->forceFill(['password' => bcrypt('right-password')])->save();
    $this->actingAs($admin);
    app(Backups::class)->run();
    $backup = Backup::sole();

    $this->postJson(p("backups/{$backup->id}/link"), ['password' => 'wrong'], idem())->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson(p("backups/{$backup->id}/link"), ['code' => '000000'], idem())->assertUnprocessable();
    $url = $this->postJson(p("backups/{$backup->id}/link"), ['password' => 'right-password'], idem())->assertOk()->json('data.url');

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/zip');
    $this->get(p("backups/{$backup->id}/file"))->assertForbidden(); // no signature
    $this->travel(6)->minutes();
    $this->get($url)->assertForbidden();

    $this->deleteJson(p("backups/{$backup->id}"), [], idem())->assertNoContent();
    expect(File::exists((string) $backup->path()))->toBeFalse();

    // Restaurants never reach it.
    [$owner] = member(Role::Owner);
    app('auth')->forgetGuards();
    $this->actingAs($owner)->getJson(p('backups'))->assertNotFound();
});

it('queues "Back up now" and copies backups off-site with an AWS Signature V4 upload', function () {
    $admin = superAdmin();
    Queue::fake();
    $this->actingAs($admin)->postJson(p('backups'), [], idem())->assertStatus(202)->assertJsonPath('data.status', 'queued');
    Queue::assertPushed(RunBackup::class);
    $this->postJson(p('backups'), [], idem())->assertStatus(409); // one at a time

    $this->putJson(p('backups/settings'), ['backup_s3_enabled' => true], idem())->assertUnprocessable();
    $this->putJson(p('backups/settings'), [
        'backup_s3_enabled' => true, 'backup_s3_endpoint' => 'https://s3.eu-central-003.backblazeb2.com', 'backup_s3_region' => 'eu-central-003',
        'backup_s3_bucket' => 'my-bucket', 'backup_s3_key' => 'AKIDEXAMPLE', 'backup_s3_secret' => 'top-secret', 'backup_keep_daily' => 10,
    ], idem())->assertOk()->assertJsonPath('data.settings.backup_s3_secret_set', true)->assertJsonMissingPath('data.settings.backup_s3_secret');
    expect(Crypt::decryptString((string) PlatformSettings::get('backup_s3_secret')))->toBe('top-secret');

    Http::fake(['*' => Http::response('', 200)]);
    Backup::query()->delete();
    config(['backups.key' => BackupCrypto::newKey()]); // off-site copies are always encrypted (H4)
    $backup = app(Backups::class)->run();
    expect($backup->offsite)->toBe('uploaded');
    Http::assertSent(fn ($r) => $r->method() === 'PUT'
        && str_starts_with($r->url(), 'https://s3.eu-central-003.backblazeb2.com/my-bucket/menu-backups/backup-')
        && str_ends_with($r->url(), '.zip.enc')
        && str_contains($r->header('Authorization')[0], 'Credential=AKIDEXAMPLE/'));
});

it('signs exactly like the AWS SDK (botocore reference vector)', function () {
    $s3 = new S3('https://s3.eu-central-003.backblazeb2.com', 'eu-central-003', 'my-bucket', 'AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY');
    $headers = $s3->sign('PUT', 'menu-backups/backup-1.zip', hash('sha256', 'hello'), ['content-type' => 'application/zip', 'content-length' => '5'], gmmktime(9, 0, 0, 11, 13, 2026));

    expect($headers['Authorization'])->toEndWith('Signature=bcab6fc58a34b2eb9336f1c2c7cababa8fdc67d8002232f623a80c6d3f02fbd0');
});

it('words the backup warning e-mail calmly and signs it like the other e-mails (pre-launch check)', function () {
    $mail = (new BackupProblemNotification('The last backup failed: disk full.'))->toMail(superAdmin());
    expect($mail->greeting)->toBe('A backup needs your attention')->not->toContain('Whoops')
        ->and($mail->salutation)->toBe('Thank you, the Menu team');
});
