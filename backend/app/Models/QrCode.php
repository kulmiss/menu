<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Qr\QrMap;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A printed code: /q/{code} → the restaurant, a branch or a table.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property int|null $branch_id
 * @property int|null $dining_table_id
 * @property string|null $label
 * @property-read Branch|null $branch
 * @property-read DiningTable|null $table
 */
#[Fillable(['code', 'branch_id', 'dining_table_id', 'label'])]
class QrCode extends Model
{
    protected static function booted(): void
    {
        // /q/{code} is answered from a file: keep it in step.
        static::saved(fn () => QrMap::touch());
        static::deleted(fn () => QrMap::touch());
        static::restored(fn () => QrMap::touch());
    }

    use BelongsToTenant;
    use SoftDeletes;

    /** No 0/O, 1/I/L: easy to read aloud and type. Upper case keeps the QR small. */
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (self::withoutGlobalScopes()->withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
