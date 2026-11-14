<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\ActivityLogger;
use App\Support\Insights\InsightsReport;
use App\Support\Insights\ReportPdf;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Insights export (owner, 2026-11-14, QUESTIONS #180): three CSV tables and
 * a PDF report for the chosen period and branch, and the monthly e-mail
 * switch. Owner and Manager only (Staff can't export); plan feature insights.
 */
class InsightsExportController extends Controller
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function csv(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('restaurant.manage');
        $table = $request->validate(['table' => ['required', Rule::in(['daily', 'qr', 'dishes'])]])['table'];
        $report = $this->report($request, $tenant);
        $t = fn (string $k) => __('insights.export.'.$k);

        [$head, $rows] = match ($table) {
            'daily' => [
                [$t('date'), $t('views'), $t('scans'), $t('item_views'), $t('order_adds')],
                array_map(fn ($d) => [$d['date'], $d['views'], $d['scans'], $d['item_views'], $d['order_adds']], $report['days']),
            ],
            'qr' => [
                [$t('code'), $t('kind'), $t('branch'), $t('scans')],
                array_map(fn ($c) => [$c['label'], __('insights.export.kinds.'.$c['kind']), $c['branch'], $c['scans']], $report['codes']),
            ],
            default => [
                [$t('dish'), $t('category'), $t('item_views'), $t('order_adds')],
                array_map(fn ($d) => [$d['name'], $d['category'], $d['views'], $d['adds']], $report['dishes']),
            ],
        };

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8
        fputcsv($out, $head, escape: '');
        foreach ($rows as $row) {
            // Text starting with = + - @ is shown as text, never run as a formula.
            fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $row), escape: '');
        }
        rewind($out);
        $name = Str::slug($tenant->name.' insights '.$table.' '.$report['from'].' '.$report['to']).'.csv';

        return response((string) stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function pdf(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('restaurant.manage');
        $report = $this->report($request, $tenant);

        return response(ReportPdf::forTenant($tenant, $report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.ReportPdf::filename($tenant, $report).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function settings(Tenant $tenant): JsonResponse
    {
        Gate::authorize('restaurant.manage');

        return response()->json(['data' => ['monthly_email' => (bool) $tenant->insights_report]]);
    }

    public function updateSettings(Request $request, Tenant $tenant): JsonResponse
    {
        Gate::authorize('restaurant.manage');
        $data = $request->validate(['monthly_email' => ['required', 'boolean']]);
        $tenant->forceFill(['insights_report' => $data['monthly_email']]);
        if ($data['monthly_email']) {
            // The first report comes on the next 1st, not straight away for last month.
            $tenant->insights_report_sent = CarbonImmutable::now($tenant->timezone)->subMonthNoOverflow()->format('Y-m');
        }
        $tenant->save();
        // Logged only: the guest page doesn't change.
        app(ActivityLogger::class)->log('insights.report', $tenant, ['monthly_email' => $data['monthly_email']]);

        return $this->settings($tenant);
    }

    /** @return array<string, mixed> */
    private function report(Request $request, Tenant $tenant): array
    {
        $data = $request->validate([
            'days' => ['sometimes', 'integer', Rule::in([7, 30, 90])],
            'from' => ['sometimes', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $this->current->id())],
        ]);
        $today = CarbonImmutable::now($tenant->timezone)->startOfDay();
        if (isset($data['from'], $data['to'])) {
            $from = CarbonImmutable::parse($data['from']);
            $to = CarbonImmutable::parse($data['to']);
            abort_if($from->diffInDays($to) > 366, 422, __('validation.max.numeric', ['attribute' => 'days', 'max' => 366]));
        } else {
            $to = $today;
            $from = $today->subDays((int) ($data['days'] ?? 30) - 1);
        }

        return InsightsReport::build($tenant, $from, $to, isset($data['branch_id']) ? (int) $data['branch_id'] : null);
    }
}
