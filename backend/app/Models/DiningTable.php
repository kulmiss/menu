<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $label
 * @property int $sort
 * @property-read Branch $branch
 * @property-read QrCode|null $qrCode
 */
#[Fillable(['branch_id', 'label', 'sort'])]
class DiningTable extends Model
{
    use BelongsToTenant;

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasOne<QrCode, $this> */
    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class);
    }
}
