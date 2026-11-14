<?php

namespace App\Models;

use App\Support\Backups\BackupCrypto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One backup file (database + media). Platform model.
 *
 * @property int $id
 * @property string|null $filename
 * @property int $size
 * @property string $kind
 * @property string $status
 * @property string|null $offsite
 * @property string|null $error
 * @property int|null $created_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
#[Fillable(['filename', 'size', 'kind', 'status', 'offsite', 'error', 'created_by', 'started_at', 'finished_at'])]
class Backup extends Model
{
    protected function casts(): array
    {
        return ['size' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function path(): ?string
    {
        return $this->filename ? self::dir().'/'.$this->filename : null;
    }

    /** Outside the public folder; never served by the web server. */
    /** Encrypted with BACKUP_KEY (SECURITY_REPORT.md H4). */
    public function encrypted(): bool
    {
        return $this->filename !== null && str_ends_with($this->filename, BackupCrypto::SUFFIX);
    }

    public static function dir(): string
    {
        return storage_path('app/backups');
    }
}
