<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Support\PlatformStats\Stats;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Super Admin dashboard widgets (QUESTIONS #186). Each widget is its own
 * request, read from the summary tables only and cached for five minutes.
 * Range: days=7|30|90|365, or from/to (at most 400 days); plan = a plan id or
 * nothing for all plans.
 */
class InsightsController extends Controller
{
    public const WIDGETS = ['overview', 'growth', 'engagement', 'usage', 'top', 'admins'];

    public function show(Request $request, string $widget): JsonResponse
    {
        abort_unless(in_array($widget, self::WIDGETS, true), 404);
        $data = $request->validate([
            'days' => ['sometimes', 'integer', Rule::in([7, 30, 90, 365])],
            'from' => ['sometimes', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'plan' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);
        $today = CarbonImmutable::today();
        if (isset($data['from'], $data['to'])) {
            $from = CarbonImmutable::parse($data['from']);
            $to = CarbonImmutable::parse($data['to']);
            if ($from->diffInDays($to) > 400) {
                $from = $to->subDays(400);
            }
        } else {
            $to = $today;
            $from = $today->subDays((int) ($data['days'] ?? 30) - 1);
        }
        $stats = new Stats($from, $to, (int) ($data['plan'] ?? 0));

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            ...$stats->cached($widget, fn () => $stats->{$widget}()),
        ]]);
    }

    /** Plans for the filter and when the numbers were last built. */
    public function meta(): JsonResponse
    {
        return response()->json(['data' => [
            'plans' => Stats::plans(),
            'computed_at' => Cache::get(Stats::VERSION_KEY),
        ]]);
    }
}
