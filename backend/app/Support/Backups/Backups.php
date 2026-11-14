<?php

namespace App\Support\Backups;

use App\Models\Backup;
use App\Models\User;
use App\Notifications\BackupProblemNotification;
use App\Support\PlatformSettings;
use App\Support\Push\Push;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Daily backups (owner, 2026-11-13): one zip in storage/app/backups with
 * database.sql (compressed) and every uploaded file under media/ (stored
 * as is: photos are already compressed). Optional off-site copy to
 * S3-compatible storage. Old backups are thinned out to the newest one per
 * day / week / month (7 / 4 / 3 by default). No restore from the screen:
 * see docs/deploy.md.
 *
 * With BACKUP_KEY set the zip is encrypted (…zip.enc) and only the encrypted
 * file is kept and copied off-site; without it there is no off-site copy
 * (SECURITY_REPORT.md H4).
 */
class Backups
{
    /** Hours without a successful backup before the Super Admins are warned. */
    public const ALERT_HOURS = 26;

    public function run(?Backup $backup = null, string $kind = 'scheduled', ?User $by = null): Backup
    {
        $backup ??= Backup::create(['kind' => $kind, 'status' => 'running', 'created_by' => $by?->id]);
        $backup->forceFill(['status' => 'running', 'started_at' => now(), 'error' => null])->save();
        if (BackupCrypto::misconfigured()) {
            $backup->forceFill(['status' => 'failed', 'error' => (string) __('backups.key_invalid'), 'finished_at' => now()])->save();
            $this->alert($backup->error);

            return $backup;
        }
        File::ensureDirectoryExists(Backup::dir(), 0700);
        $name = 'backup-'.now()->format('Ymd-His').'-'.$backup->id.'.zip';
        $path = Backup::dir().'/'.$name;
        $sql = tempnam(sys_get_temp_dir(), 'db');

        try {
            $out = fopen((string) $sql, 'wb');
            $rows = DatabaseDump::write($out);
            fclose($out);

            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot create '.$path);
            }
            $zip->addFile((string) $sql, 'database.sql');
            $files = $this->addMedia($zip);
            $zip->addFromString('manifest.json', (string) json_encode([
                'created_at' => now()->toIso8601String(), 'app' => config('app.name'), 'laravel' => app()->version(),
                'database' => ['rows' => $rows], 'media' => ['files' => $files], 'restore' => 'See docs/deploy.md, "Restoring a backup".',
            ], JSON_PRETTY_PRINT));
            if (! $zip->close()) {
                throw new RuntimeException('Writing the zip failed (disk full?)');
            }
            @chmod($path, 0600);
            if (($key = BackupCrypto::key()) !== null) {
                BackupCrypto::encryptFile($path, $path.BackupCrypto::SUFFIX, $key);
                @unlink($path);
                $name .= BackupCrypto::SUFFIX;
                $path .= BackupCrypto::SUFFIX;
            }
            $backup->forceFill(['filename' => $name, 'size' => (int) filesize($path), 'status' => 'ok', 'finished_at' => now()])->save();
        } catch (Throwable $e) {
            @unlink($path);
            @unlink($path.BackupCrypto::SUFFIX);
            $backup->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => now()])->save();
            $this->alert($backup->error);

            return $backup;
        } finally {
            @unlink((string) $sql);
        }

        $this->offsite($backup);
        $this->prune();

        return $backup;
    }

    /** Copies the file to S3-compatible storage when switched on. */
    public function offsite(Backup $backup): void
    {
        $client = self::s3();
        if ($client === null || $backup->path() === null) {
            return;
        }
        if (! $backup->encrypted()) {
            // Never send a readable copy of every restaurant's data to a third party.
            $backup->forceFill(['offsite' => 'failed', 'error' => (string) __('backups.offsite_needs_key')])->save();
            $this->alert($backup->error);

            return;
        }
        try {
            $client->put((string) $backup->path(), self::objectKey((string) $backup->filename));
            $backup->forceFill(['offsite' => 'uploaded'])->save();
        } catch (Throwable $e) {
            $backup->forceFill(['offsite' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
            $this->alert($backup->error);
        }
    }

    /**
     * Keeps the newest successful backup of each of the last N days, weeks
     * and months; deletes the rest (and their off-site copies). Failed
     * records are kept 30 days for the history.
     */
    public function prune(): int
    {
        $s = PlatformSettings::all();
        $ok = Backup::where('status', 'ok')->orderByDesc('created_at')->orderByDesc('id')->get();
        $keep = [];
        foreach ([['Y-m-d', (int) $s['backup_keep_daily']], ['o-W', (int) $s['backup_keep_weekly']], ['Y-m', (int) $s['backup_keep_monthly']]] as [$format, $count]) {
            $seen = [];
            foreach ($ok as $b) {
                $bucket = $b->created_at->format($format);
                if (! isset($seen[$bucket]) && count($seen) < $count) {
                    $seen[$bucket] = true;
                    $keep[$b->id] = true;
                }
            }
        }
        $deleted = 0;
        foreach ($ok as $b) {
            if (! isset($keep[$b->id])) {
                $this->delete($b);
                $deleted++;
            }
        }
        Backup::where('status', 'failed')->where('created_at', '<', now()->subDays(30))->delete();

        return $deleted;
    }

    public function delete(Backup $backup): void
    {
        if ($backup->path() !== null) {
            @unlink((string) $backup->path());
        }
        if ($backup->offsite === 'uploaded' && ($client = self::s3()) !== null) {
            try {
                $client->delete(self::objectKey((string) $backup->filename));
            } catch (Throwable) {
                // The local copy is gone either way; the remote one can be removed by hand.
            }
        }
        $backup->delete();
    }

    /** Warns the Super Admins when the last backup failed or none succeeded in 26 h (once a day). */
    public function check(): ?string
    {
        $problem = self::problem();
        if ($problem !== null) {
            $this->alert($problem);
        }

        return $problem;
    }

    public static function problem(): ?string
    {
        $last = Backup::whereIn('status', ['ok', 'failed'])->latest('id')->first();
        if ($last?->status === 'failed') {
            return (string) __('backups.problem_failed', ['error' => (string) $last->error]);
        }
        $ok = Backup::where('status', 'ok')->latest('finished_at')->value('finished_at');
        if ($ok === null || Carbon::parse($ok)->lt(now()->subHours(self::ALERT_HOURS))) {
            return (string) __('backups.problem_none', ['hours' => self::ALERT_HOURS]);
        }

        return null;
    }

    public static function s3(): ?S3
    {
        $s = PlatformSettings::all();
        if (! $s['backup_s3_enabled'] || $s['backup_s3_endpoint'] === '' || $s['backup_s3_bucket'] === '') {
            return null;
        }
        try {
            $secret = $s['backup_s3_secret'] !== '' ? Crypt::decryptString((string) $s['backup_s3_secret']) : '';
        } catch (DecryptException) {
            $secret = '';
        }

        return new S3((string) $s['backup_s3_endpoint'], (string) ($s['backup_s3_region'] ?: 'auto'), (string) $s['backup_s3_bucket'], (string) $s['backup_s3_key'], $secret);
    }

    private static function objectKey(string $filename): string
    {
        $prefix = trim((string) PlatformSettings::get('backup_s3_prefix'), '/');

        return ($prefix !== '' ? $prefix.'/' : '').$filename;
    }

    private function addMedia(ZipArchive $zip): int
    {
        $root = public_path('media');
        if (! is_dir($root)) {
            return 0;
        }
        $count = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $local = 'media/'.ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            $zip->addFile($file->getPathname(), $local);
            $zip->setCompressionName($local, ZipArchive::CM_STORE);
            $count++;
        }

        return $count;
    }

    private function alert(?string $problem): void
    {
        if (! Cache::add('backups:alert', true, now()->addDay())) {
            return; // at most one e-mail a day
        }
        Push::send(Push::superAdmins(), 'backup_failed', __('push.types.backup_failed'), (string) $problem, '/p/backups');
        Notification::send(User::where('is_super_admin', true)->get(), new BackupProblemNotification((string) $problem));
    }
}
