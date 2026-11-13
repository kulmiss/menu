<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Support\Backups\Backups;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** "Back up now" from the Backups screen, run by the cron queue worker. */
class RunBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly int $backupId) {}

    public function handle(Backups $backups): void
    {
        $backup = Backup::find($this->backupId);
        if ($backup !== null && $backup->status === 'queued') {
            $backups->run($backup);
        }
    }
}
