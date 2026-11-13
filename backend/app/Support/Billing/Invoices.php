<?php

namespace App\Support\Billing;

use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\InvoiceNotification;
use App\Support\ActivityLogger;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Issues, voids and sends invoices (owner, 2026-11-13).
 *
 * Numbers: "{prefix}-{year}-{0001}", one counter per prefix and year in
 * invoice_sequences. The counter row is locked (SELECT … FOR UPDATE) in the
 * same transaction that writes the invoice, so two Super Admins recording
 * payments at the same moment wait for each other, and a failed insert rolls
 * the counter back: numbers are never duplicated and never skipped.
 *
 * The amount paid is the total; an optional VAT % is shown as included in it.
 */
class Invoices
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /** Issues an invoice for a payment. Call inside or outside a transaction. */
    public function issue(Payment $payment, ?User $by = null, ?Invoice $replaces = null, ?string $note = null): Invoice
    {
        return DB::transaction(function () use ($payment, $by, $replaces, $note) {
            $s = PlatformSettings::all();
            $prefix = self::prefix((string) $s['invoice_prefix']);
            $issuedAt = now();
            $number = $this->next($prefix, (int) $issuedAt->format('Y'));

            $rate = round(max(0.0, min(100.0, (float) $s['invoice_tax_rate'])), 2);
            $total = $payment->amount_minor;
            $subtotal = (int) round($total / (1 + $rate / 100));
            $tenant = $payment->tenant;
            $owner = self::owner($tenant->id);

            $invoice = Invoice::create([
                'number' => $number,
                'payment_id' => $payment->id,
                'tenant_id' => $payment->tenant_id,
                'plan_id' => $payment->plan_id,
                'seller' => [
                    'name' => trim((string) $s['billing_company']) ?: (string) $s['name'],
                    'address' => (string) $s['billing_address'],
                    'tax_id' => (string) $s['billing_tax_id'],
                    'footer' => (string) $s['invoice_footer'],
                    'email' => (string) ($s['support_email'] ?? ''),
                    'logo' => is_string($s['logo']) && $s['logo'] !== '' ? $s['logo'] : null,
                ],
                'customer' => [
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'address' => self::address($tenant->id),
                    'owner' => (string) ($owner->name ?? ''),
                    'email' => (string) ($owner->email ?? ''),
                ],
                'plan_name' => $payment->plan ? ($payment->plan->name['en'] ?? $payment->plan->slug) : null,
                'period_start' => $payment->period_start,
                'period_end' => $payment->period_end,
                'currency' => $payment->currency,
                'subtotal_minor' => $subtotal,
                'tax_rate' => $rate,
                'tax_minor' => $total - $subtotal,
                'total_minor' => $total,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at,
                'status' => Invoice::PAID,
                'note' => $note ?? $payment->notes,
                'issued_by' => $by?->id,
                'issued_at' => $issuedAt,
                'replaces_id' => $replaces?->id,
            ]);
            $this->activity->log('platform.invoice_issued', $invoice, ['number' => ['to' => $number]], null);

            return $invoice;
        });
    }

    /** Voids an invoice (kept, marked void) and, if asked, issues its replacement. */
    public function void(Invoice $invoice, string $reason, ?User $by = null, bool $reissue = false): ?Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $by, $reissue) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === Invoice::VOID, 409, __('platform.invoice_already_void'));
            $locked->forceFill(['status' => Invoice::VOID, 'void_reason' => $reason, 'voided_at' => now(), 'voided_by' => $by?->id])->save();
            $this->activity->log('platform.invoice_voided', $locked, ['reason' => ['to' => $reason]], null);
            $invoice->setRawAttributes($locked->getAttributes(), true);

            if (! $reissue || $locked->payment === null) {
                return null;
            }

            return $this->issue($locked->payment, $by, $locked);
        });
    }

    /** E-mails the invoice PDF to the restaurant's owners. */
    public function send(Invoice $invoice): int
    {
        $owners = $invoice->tenant_id === null ? collect() : User::whereIn('id', DB::table('memberships')
            ->where('tenant_id', $invoice->tenant_id)->where('role', Role::Owner->value)->pluck('user_id'))->get();
        if ($owners->isEmpty()) {
            return 0;
        }
        Notification::send($owners, new InvoiceNotification($invoice->id));
        $invoice->forceFill(['emailed_at' => now()])->save();

        return $owners->count();
    }

    public static function prefix(string $prefix): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $prefix));

        return substr($clean, 0, 10) ?: 'INV';
    }

    /** Next number for this prefix and year; locks the counter until the transaction ends. */
    private function next(string $prefix, int $year): string
    {
        $key = "{$prefix}-{$year}";
        DB::table('invoice_sequences')->insertOrIgnore(['key' => $key, 'last' => 0]);
        $last = (int) DB::table('invoice_sequences')->where('key', $key)->lockForUpdate()->value('last');
        DB::table('invoice_sequences')->where('key', $key)->update(['last' => $last + 1]);

        return sprintf('%s-%04d', $key, $last + 1);
    }

    private static function owner(int $tenantId): ?User
    {
        $id = DB::table('memberships')->where('tenant_id', $tenantId)->where('role', Role::Owner->value)->orderBy('id')->value('user_id');

        return $id ? User::find($id) : null;
    }

    private static function address(int $tenantId): string
    {
        $raw = DB::table('tenant_profiles')->where('tenant_id', $tenantId)->value('address');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? (string) ($decoded['en'] ?? reset($decoded) ?: '') : '';
    }
}
