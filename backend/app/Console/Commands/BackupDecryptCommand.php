<?php

namespace App\Console\Commands;

use App\Support\Backups\BackupCrypto;
use Illuminate\Console\Command;
use RuntimeException;

/** Turns backup-….zip.enc back into the zip, for a restore (SECURITY_REPORT.md H4). */
class BackupDecryptCommand extends Command
{
    protected $signature = 'backup:decrypt {file : The .zip.enc file} {out? : Where to write the zip (default: next to it)} {--key= : The BACKUP_KEY, if this server has another one}';

    protected $description = 'Decrypt an encrypted backup';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $out = (string) ($this->argument('out') ?: preg_replace('/\.enc$/', '', $file));
        if ($out === $file) {
            $out .= '.zip';
        }
        if ($this->option('key')) {
            config(['backups.key' => (string) $this->option('key')]);
        }
        $key = BackupCrypto::key();
        if ($key === null) {
            $this->error('No valid key: set BACKUP_KEY in .env or pass --key=base64:…');

            return self::FAILURE;
        }
        if (! is_file($file)) {
            $this->error('File not found: '.$file);

            return self::FAILURE;
        }
        try {
            BackupCrypto::decryptFile($file, $out, $key);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Written: '.$out);

        return self::SUCCESS;
    }
}
