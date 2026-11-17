<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Counts clicks on the landing page's main buttons (owner, 2026-11-17),
 * sent by the page with navigator.sendBeacon. Privacy-friendly: no cookie,
 * no session, nothing stored about the visitor (not even the address) —
 * only "one more click on this button today". Super Admin → Landing page
 * shows the numbers.
 */
class LandingClickController extends Controller
{
    /** The buttons that are counted. */
    public const BUTTONS = ['signup', 'google', 'demo'];

    public function __invoke(Request $request): Response
    {
        $button = (string) $request->input('k');
        if (in_array($button, self::BUTTONS, true)) {
            DB::table('landing_clicks')->upsert(
                [['day' => now()->toDateString(), 'button' => $button, 'count' => 1]],
                ['day', 'button'],
                ['count' => DB::raw('count + 1')],
            );
        }

        return response()->noContent();
    }

    /**
     * The last $days days, oldest first, with zeros for quiet days.
     *
     * @return array{days: list<array{day: string, signup: int, google: int, demo: int}>, totals: array<string, int>, all_time: array<string, int>}
     */
    public static function report(int $days = 30): array
    {
        $from = now()->subDays($days - 1)->toDateString();
        $rows = DB::table('landing_clicks')->where('day', '>=', $from)->get(['day', 'button', 'count']);
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[substr((string) $r->day, 0, 10)][$r->button] = (int) $r->count;
        }
        $list = [];
        $totals = array_fill_keys(self::BUTTONS, 0);
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $entry = ['day' => $day];
            foreach (self::BUTTONS as $b) {
                $entry[$b] = $byDay[$day][$b] ?? 0;
                $totals[$b] += $entry[$b];
            }
            $list[] = $entry;
        }
        $allTime = array_fill_keys(self::BUTTONS, 0);
        foreach (DB::table('landing_clicks')->selectRaw('button, SUM(count) as n')->groupBy('button')->get() as $r) {
            if (isset($allTime[$r->button])) {
                $allTime[$r->button] = (int) $r->n;
            }
        }

        /** @var list<array{day: string, signup: int, google: int, demo: int}> $list */
        return ['days' => $list, 'totals' => $totals, 'all_time' => $allTime];
    }
}
