<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Insights counters (PLAN.md §4.9): one upsert per counted event.
 */
class Stats
{
    public const METRICS = ['menu_view', 'qr_scan', 'item_view'];

    /** User agents that are not people: crawlers, link previews, speed tests. */
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|headless|lighthouse|pagespeed|curl|wget|python|httpclient|monitor/i';

    public static function isBot(?string $userAgent): bool
    {
        return $userAgent === null || $userAgent === '' || (bool) preg_match(self::BOTS, $userAgent);
    }

    public static function hit(int $tenantId, string $date, int $branchId, string $metric, int $subjectId = 0): void
    {
        DB::statement(
            'INSERT INTO stats_daily (tenant_id, date, branch_id, metric, subject_id, count) VALUES (?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE count = count + 1',
            [$tenantId, $date, $branchId, $metric, $subjectId],
        );
    }
}
