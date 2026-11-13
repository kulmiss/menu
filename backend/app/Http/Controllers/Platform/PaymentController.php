<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\ActivityLogger;
use App\Support\Billing\Invoices;
use App\Support\PlatformSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Super Admin › Payments (PLAN.md §7.3 screen 4). Payments are recorded by
 * hand; each one moves the restaurant's paid_until to the end of its period.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity, private readonly Invoices $invoices) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->filtered($request)->limit(1000)->get()->map(fn (Payment $p) => self::row($p))]);
    }

    public function store(Request $request, int $restaurant): JsonResponse
    {
        $tenant = Tenant::findOrFail($restaurant);
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date_format:Y-m-d'],
            'months' => ['required_without:period_end', 'nullable', 'integer', 'between:1,36'],
            'period_start' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'period_end' => ['required_without:months', 'nullable', 'date_format:Y-m-d', 'after:period_start'],
            'plan_id' => ['sometimes', 'nullable', 'integer', Rule::exists('plans', 'id')],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        // The new period starts where the paid time ends (or today if it already ran out).
        $start = isset($data['period_start'])
            ? Carbon::parse($data['period_start'])
            : (($tenant->paid_until && $tenant->paid_until->isFuture()) ? $tenant->paid_until->copy()->startOfDay() : now()->startOfDay());
        $end = isset($data['period_end']) ? Carbon::parse($data['period_end']) : $start->copy()->addMonthsNoOverflow((int) $data['months']);

        $payment = DB::transaction(function () use ($tenant, $data, $start, $end, $request) {
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $data['plan_id'] ?? $tenant->plan_id,
                'amount_minor' => $data['amount_minor'],
                'currency' => strtoupper($data['currency'] ?? $tenant->currency_code),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'],
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'recorded_by' => $request->user()?->getAuthIdentifier(),
                'notes' => $data['notes'] ?? null,
            ]);
            if ($tenant->paid_until === null || $end->gt($tenant->paid_until)) {
                $tenant->forceFill(['paid_until' => $end->copy()->endOfDay()])->save();
            }
            if (isset($data['plan_id'])) {
                $tenant->forceFill(['plan_id' => $data['plan_id']])->save();
            }
            // Every payment gets its invoice in the same transaction (owner, 2026-11-13).
            $this->invoices->issue($payment->setRelation('tenant', $tenant), $request->user());

            return $payment;
        });
        if (PlatformSettings::get('invoice_email')) {
            $invoice = Invoice::where('payment_id', $payment->id)->latest('id')->first();
            if ($invoice !== null) {
                $this->invoices->send($invoice);
            }
        }
        $tenant->bumpCacheVersion();
        $this->activity->log('platform.payment_recorded', $payment, ['amount' => ['to' => $payment->amount_minor], 'period_end' => ['to' => $payment->period_end->toDateString()]], null);

        return response()->json(['data' => self::row($payment->load(['tenant', 'plan', 'invoices']))], 201);
    }

    /** CSV for the accountant (opens in Excel; UTF-8 with BOM). */
    public function export(Request $request): Response
    {
        $rows = $this->filtered($request)->get();
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Paid on', 'Restaurant', 'Amount', 'Currency', 'Method', 'Reference', 'Period start', 'Period end', 'Plan', 'Notes'], escape: '');
        foreach ($rows as $p) {
            fputcsv($out, [
                $p->paid_at->toDateString(), $p->tenant->name, number_format($p->amount_minor / 100, 2, '.', ''), $p->currency, $p->method,
                $p->reference, $p->period_start->toDateString(), $p->period_end->toDateString(), $p->plan?->name['en'] ?? '', $p->notes,
            ], escape: '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="payments.csv"']);
    }

    /** @return Builder<Payment> */
    private function filtered(Request $request): Builder
    {
        $data = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'tenant_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        return Payment::with(['tenant', 'plan', 'invoices'])
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('paid_at', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('paid_at', '<=', $d))
            ->when($data['tenant_id'] ?? null, fn ($q, $t) => $q->where('tenant_id', $t))
            ->latest('paid_at')->latest('id');
    }

    /** @return array<string, mixed> */
    public static function row(Payment $p): array
    {
        return [
            'id' => $p->id,
            'tenant' => $p->relationLoaded('tenant') ? ['id' => $p->tenant->id, 'name' => $p->tenant->name] : null,
            'plan' => $p->plan ? ($p->plan->name['en'] ?? $p->plan->slug) : null,
            'amount_minor' => $p->amount_minor,
            'currency' => $p->currency,
            'method' => $p->method,
            'reference' => $p->reference,
            'paid_at' => $p->paid_at->toDateString(),
            'period_start' => $p->period_start->toDateString(),
            'period_end' => $p->period_end->toDateString(),
            'notes' => $p->notes,
            'invoices' => $p->relationLoaded('invoices') ? $p->invoices->sortByDesc('id')->map(fn (Invoice $i) => ['id' => $i->id, 'number' => $i->number, 'status' => $i->status])->values()->all() : [],
        ];
    }
}
