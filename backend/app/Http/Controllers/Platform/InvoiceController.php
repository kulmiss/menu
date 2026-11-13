<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Billing\InvoicePdf;
use App\Support\Billing\Invoices;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Super Admin › Invoices (owner, 2026-11-13): list, CSV, PDF, resend, void. */
class InvoiceController extends Controller
{
    public function __construct(private readonly Invoices $invoices) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->filtered($request)->limit(500)->get()->map(fn (Invoice $i) => self::row($i))]);
    }

    public function export(Request $request): Response
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Number', 'Issued', 'Status', 'Restaurant', 'Owner e-mail', 'Plan', 'Period start', 'Period end', 'Subtotal', 'VAT %', 'VAT', 'Total', 'Currency', 'Method', 'Reference', 'Paid on', 'Void reason', 'Replaces'], escape: '');
        foreach ($this->filtered($request)->with('replaces')->get() as $i) {
            $money = fn (int $m) => number_format($m / 100, 2, '.', '');
            fputcsv($out, [
                $i->number, $i->issued_at->toDateString(), $i->status, $i->customer['name'] ?? '', $i->customer['email'] ?? '', $i->plan_name,
                $i->period_start->toDateString(), $i->period_end->toDateString(), $money($i->subtotal_minor), $i->tax_rate, $money($i->tax_minor),
                $money($i->total_minor), $i->currency, $i->method, $i->reference, $i->paid_at->toDateString(), $i->void_reason, $i->replaces?->number,
            ], escape: '');
        }
        rewind($out);

        return response((string) stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="invoices.csv"']);
    }

    public function pdf(int $invoice): Response
    {
        $model = Invoice::with('replaces')->findOrFail($invoice);

        return self::pdfResponse($model);
    }

    public function resend(int $invoice): JsonResponse
    {
        $model = Invoice::findOrFail($invoice);
        $sent = $this->invoices->send($model);
        abort_if($sent === 0, 422, __('platform.invoice_no_owner'));

        return response()->json(['data' => self::row($model->fresh() ?? $model)]);
    }

    public function void(Request $request, int $invoice): JsonResponse
    {
        $model = Invoice::findOrFail($invoice);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:300'],
            'reissue' => ['sometimes', 'boolean'],
        ]);
        $new = $this->invoices->void($model, $data['reason'], $request->user(), (bool) ($data['reissue'] ?? false));

        return response()->json(['data' => ['void' => self::row($model->fresh() ?? $model), 'new' => $new ? self::row($new) : null]]);
    }

    public static function pdfResponse(Invoice $invoice): Response
    {
        return response(InvoicePdf::render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.InvoicePdf::filename($invoice).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, mixed> */
    public static function row(Invoice $i): array
    {
        return [
            'id' => $i->id,
            'number' => $i->number,
            'status' => $i->status,
            'issued_at' => $i->issued_at->toIso8601String(),
            'tenant' => ['id' => $i->tenant_id, 'name' => $i->customer['name'] ?? ''],
            'owner_email' => $i->customer['email'] ?? '',
            'plan' => $i->plan_name,
            'period_start' => $i->period_start->toDateString(),
            'period_end' => $i->period_end->toDateString(),
            'total_minor' => $i->total_minor,
            'currency' => $i->currency,
            'payment_id' => $i->payment_id,
            'void_reason' => $i->void_reason,
            'replaces_id' => $i->replaces_id,
            'emailed_at' => $i->emailed_at?->toIso8601String(),
        ];
    }

    /** @return Builder<Invoice> */
    private function filtered(Request $request): Builder
    {
        $data = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'tenant_id' => ['sometimes', 'nullable', 'integer'],
            'plan_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', 'in:paid,void'],
        ]);

        return Invoice::query()
            ->when($data['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('customer->name', 'like', $like)->orWhere('customer->email', 'like', $like));
            })
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('issued_at', '>=', $d.' 00:00:00'))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('issued_at', '<=', $d.' 23:59:59'))
            ->when($data['tenant_id'] ?? null, fn ($q, $t) => $q->where('tenant_id', $t))
            ->when($data['plan_id'] ?? null, fn ($q, $p) => $q->where('plan_id', $p))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest('issued_at')->latest('id');
    }
}
