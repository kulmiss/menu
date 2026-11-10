<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment recorded by the Super Admin (manual: cash, bank, mobile money).
 * Platform model: read across restaurants only in the Platform area.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $plan_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $method
 * @property string|null $reference
 * @property Carbon $paid_at
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int|null $recorded_by
 * @property string|null $notes
 * @property-read Tenant $tenant
 * @property-read Plan|null $plan
 */
#[Fillable(['tenant_id', 'plan_id', 'amount_minor', 'currency', 'method', 'reference', 'paid_at', 'period_start', 'period_end', 'recorded_by', 'notes'])]
class Payment extends Model
{
    public const METHODS = ['cash', 'bank', 'mobile_money', 'other'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'period_start' => 'date', 'period_end' => 'date', 'amount_minor' => 'integer'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
