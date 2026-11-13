<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\InvoiceController;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Settings → Billing (owner only, owner 2026-11-13): plan, paid until, invoices. */
class BillingController extends Controller
{
    public function show(Tenant $tenant): JsonResponse
    {
        Gate::authorize('account.manage');
        $plan = $tenant->plan_id ? Plan::find($tenant->plan_id) : null;
        $invoices = Invoice::where('tenant_id', $tenant->id)->latest('issued_at')->latest('id')->get();

        return response()->json(['data' => [
            'plan' => $plan ? ['name' => $plan->name['en'] ?? $plan->slug, 'price_minor' => $plan->price_minor, 'currency' => $plan->currency, 'interval' => $plan->interval] : null,
            'paid_until' => $tenant->paid_until?->toDateString(),
            'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
            'state' => $tenant->subscriptionState()->value,
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id, 'number' => $i->number, 'status' => $i->status, 'issued_at' => $i->issued_at->toIso8601String(),
                'period_start' => $i->period_start->toDateString(), 'period_end' => $i->period_end->toDateString(),
                'total_minor' => $i->total_minor, 'currency' => $i->currency,
            ])->values(),
        ]]);
    }

    public function pdf(Tenant $tenant, int $invoice): Response
    {
        Gate::authorize('account.manage');
        $model = Invoice::with('replaces')->where('tenant_id', $tenant->id)->findOrFail($invoice);

        return InvoiceController::pdfResponse($model);
    }
}
