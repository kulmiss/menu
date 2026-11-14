<?php

namespace App\Console\Commands;

use App\Support\Backups\Backups;
use App\Support\PlatformSettings;
use Illuminate\Console\Command;

/** Daily backup (scheduler) or by hand: php artisan backup:run */
class RunBackupCommand extends Command
{
    protected $signature = 'backup:run {--scheduled : Skip when backups are switched off}';

    protected $description = 'Back up the database and uploaded media to storage/app/backups (and off-site if set).';

    public function handle(Backups $backups): int
    {
        if ($this->option('scheduled') && ! PlatformSettings::get('backup_enabled')) {
            return self::SUCCESS;
        }
        $backup = $backups->run(kind: $this->option('scheduled') ? 'scheduled' : 'manual');
        if ($backup->status !== 'ok') {
            $this->error((string) $backup->error);

            return self::FAILURE;
        }
        $this->info(sprintf('%s (%s MB)%s', $backup->filename, number_format($backup->size / 1048576, 1), $backup->offsite ? ', off-site: '.$backup->offsite : ''));

        return self::SUCCESS;
    }
}
