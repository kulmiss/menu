<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invoice for a recorded payment. Never edited or deleted: a wrong one is
 * voided (with a reason) and a new one issued. Platform model.
 *
 * @property int $id
 * @property string $number
 * @property int|null $payment_id
 * @property int|null $tenant_id
 * @property int|null $plan_id
 * @property array<string, string|null> $seller name, address, tax_id, footer, email, logo
 * @property array<string, string|null> $customer name, slug, address, owner, email
 * @property string|null $plan_name
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $currency
 * @property int $subtotal_minor
 * @property string $tax_rate
 * @property int $tax_minor
 * @property int $total_minor
 * @property string $method
 * @property string|null $reference
 * @property Carbon $paid_at
 * @property string $status
 * @property string|null $note
 * @property string|null $void_reason
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property int|null $replaces_id
 * @property int|null $issued_by
 * @property Carbon $issued_at
 * @property Carbon|null $emailed_at
 * @property-read Tenant|null $tenant
 * @property-read Payment|null $payment
 * @property-read Invoice|null $replaces
 */
#[Fillable(['number', 'payment_id', 'tenant_id', 'plan_id', 'seller', 'customer', 'plan_name', 'period_start', 'period_end', 'currency', 'subtotal_minor', 'tax_rate', 'tax_minor', 'total_minor', 'method', 'reference', 'paid_at', 'status', 'note', 'issued_by', 'issued_at', 'replaces_id'])]
class Invoice extends Model
{
    public const PAID = 'paid';

    public const VOID = 'void';

    protected function casts(): array
    {
        return [
            'seller' => 'array', 'customer' => 'array',
            'period_start' => 'date', 'period_end' => 'date', 'paid_at' => 'date',
            'issued_at' => 'datetime', 'voided_at' => 'datetime', 'emailed_at' => 'datetime',
            'subtotal_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'replaces_id');
    }
}
