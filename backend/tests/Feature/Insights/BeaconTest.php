<?php

use App\Models\Branch;
use App\Models\QrCode;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

const BROWSER = 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';

function beacon(array $body, string $agent = BROWSER, array $headers = []): TestResponse
{
    return test()->call('POST', '/api/v1/beacon', [], [], [], [
        'HTTP_USER_AGENT' => $agent,
        'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        ...$headers,
    ], json_encode($body));
}

function counts(Tenant $tenant): array
{
    return DB::table('stats_daily')->where('tenant_id', $tenant->id)->orderBy('metric')->get(['metric', 'branch_id', 'subject_id', 'count'])
        ->map(fn ($r) => [$r->metric, (int) $r->branch_id, (int) $r->subject_id, (int) $r->count])->all();
}

it('counts a menu view per page load and a scan when the page came from a QR code', function () {
    $tenant = Tenant::factory()->create();
    $qr = asTenant($tenant, fn () => QrCode::firstOrFail());
    $branch = asTenant($tenant, fn () => Branch::firstOrFail());

    beacon(['t' => $tenant->id, 'b' => $branch->id])->assertNoContent();
    beacon(['t' => $tenant->id, 'b' => $branch->id, 'q' => $qr->code])->assertNoContent();

    expect(counts($tenant))->toBe([
        ['menu_view', $branch->id, 0, 2],
        ['qr_scan', $branch->id, $qr->id, 1],
    ]);
});

it('works from the guest page itself: no session, no CSRF token', function () {
    $tenant = Tenant::factory()->create();

    beacon(['t' => $tenant->id], headers: ['HTTP_ORIGIN' => config('app.url'), 'HTTP_REFERER' => config('app.url').'/'.$tenant->slug])
        ->assertNoContent()
        ->assertCookieMissing(config('session.cookie'));

    expect(counts($tenant))->toBe([['menu_view', 0, 0, 1]]);
});

it('counts item views from the details sheet', function () {
    $tenant = Tenant::factory()->create();
    [, , $item] = sampleMenu($tenant);

    beacon(['t' => $tenant->id, 'i' => $item->id])->assertNoContent();
    beacon(['t' => $tenant->id, 'i' => $item->id])->assertNoContent();

    expect(counts($tenant))->toBe([['item_view', 0, $item->id, 2]]);
});

it('ignores bots, junk and other restaurants\' ids', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    [, , $theirItem] = sampleMenu($other);
    $theirCode = asTenant($other, fn () => QrCode::firstOrFail());
    $theirBranch = asTenant($other, fn () => Branch::firstOrFail());

    beacon(['t' => $tenant->id], 'Googlebot/2.1 (+http://www.google.com/bot.html)')->assertNoContent();
    beacon(['t' => $tenant->id], 'Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/120 Chrome-Lighthouse')->assertNoContent();
    beacon(['t' => $tenant->id], '')->assertNoContent();
    test()->call('POST', '/api/v1/beacon', [], [], [], ['HTTP_USER_AGENT' => BROWSER], 'not json')->assertNoContent();
    beacon(['t' => 999999])->assertNoContent();
    beacon(['t' => $tenant->id, 'i' => $theirItem->id])->assertNoContent();
    beacon(['t' => $tenant->id, 'b' => $theirBranch->id, 'q' => $theirCode->code])->assertNoContent();

    expect(counts($tenant))->toBe([['menu_view', 0, 0, 1]])
        ->and(counts($other))->toBe([]);
});
