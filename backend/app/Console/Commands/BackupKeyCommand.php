<?php

namespace App\Console\Commands;

use App\Support\Backups\BackupCrypto;
use Illuminate\Console\Command;

/** Makes a BACKUP_KEY (SECURITY_REPORT.md H4). */
class BackupKeyCommand extends Command
{
    protected $signature = 'backup:key {--write : Add it to .env when .env has no BACKUP_KEY yet}';

    protected $description = 'Make a key that encrypts the backups';

    public function handle(): int
    {
        $key = BackupCrypto::newKey();

        if ($this->option('write')) {
            $env = base_path('.env');
            $text = is_file($env) ? (string) file_get_contents($env) : '';
            if (preg_match('/^BACKUP_KEY=\S/m', $text) === 1) {
                $this->error('.env already has a BACKUP_KEY. Changing it makes older backups unreadable without the old key; edit .env by hand if you really mean it.');

                return self::FAILURE;
            }
            $text = preg_replace('/^BACKUP_KEY=\s*$/m', '', $text) ?? $text;
            file_put_contents($env, rtrim($text)."\nBACKUP_KEY=".$key."\n");
            $this->info('Added BACKUP_KEY to .env.');
        }

        $this->line('BACKUP_KEY='.$key);
        $this->warn('Save this key outside the server (e.g. in a password manager). Without it no backup can be restored.');

        return self::SUCCESS;
    }
}
