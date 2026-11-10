<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Stats;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Visit beacon from the guest page, sent after load (PLAN.md §15.2.7):
 * {t: restaurant, b: branch, q: QR code (scan), i: item (details opened)}.
 * Always answers 204 so a guest's page never shows an error; bad or bot
 * requests are simply not counted. 2–3 small queries, no session.
 */
class BeaconController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = json_decode((string) $request->getContent(), true);
        if (! is_array($data) || Stats::isBot($request->userAgent()) || ! is_int($data['t'] ?? null)) {
            return response()->noContent();
        }

        $tenant = DB::table('tenants')->where('id', $data['t'])->whereNull('deleted_at')->first(['id', 'timezone']);
        if ($tenant === null) {
            return response()->noContent();
        }
        $tenantId = (int) $tenant->id;
        $date = now((string) $tenant->timezone)->toDateString();
        $branch = is_int($data['b'] ?? null) ? $data['b'] : 0;
        if ($branch !== 0 && ! DB::table('branches')->where('id', $branch)->where('tenant_id', $tenantId)->exists()) {
            $branch = 0;
        }

        if (is_int($data['i'] ?? null)) {
            if (DB::table('items')->where('id', $data['i'])->where('tenant_id', $tenantId)->exists()) {
                Stats::hit($tenantId, $date, $branch, 'item_view', $data['i']);
            }

            return response()->noContent();
        }

        Stats::hit($tenantId, $date, $branch, 'menu_view');
        if (is_string($data['q'] ?? null) && preg_match('/^[A-Z0-9]{8}$/', $data['q'])) {
            $qr = DB::table('qr_codes')->where('code', $data['q'])->where('tenant_id', $tenantId)->value('id');
            if ($qr !== null) {
                Stats::hit($tenantId, $date, $branch, 'qr_scan', (int) $qr);
            }
        }

        return response()->noContent();
    }
}
