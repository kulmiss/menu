<?php

use App\Enums\Role;
use App\Models\Item;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Notifications\MonthlyInsightsNotification;
use App\Support\Features;
use App\Support\Stats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/** Ten days of numbers ending today, plus a quieter period before them. */
function insightsData(Tenant $tenant): array
{
    [, , $macchiato] = sampleMenu($tenant);
    $toast = asTenant($tenant, fn () => Item::create(['category_id' => $macchiato->category_id, 'name' => ['en' => '=Avocado toast'], 'price_minor' => 30000]));
    $code = asTenant($tenant, fn () => QrCode::firstOrFail());
    $window = asTenant($tenant, fn () => QrCode::create(['code' => QrCode::newCode(), 'label' => 'Window poster']));
    $today = now($tenant->timezone)->startOfDay();
    for ($i = 0; $i < 10; $i++) {
        $date = $today->copy()->subDays($i)->toDateString();
        Stats::hit($tenant->id, $date, 0, 'menu_view', 0, 10);
        Stats::hit($tenant->id, $date, 0, 'qr_scan', $code->id, 4);
        Stats::hit($tenant->id, $date, 0, 'qr_scan', $window->id, 1);
        Stats::hit($tenant->id, $date, 0, 'item_view', $macchiato->id, 3);
        Stats::hit($tenant->id, $date, 0, 'item_view', $toast->id, 1);
        Stats::hit($tenant->id, $date, 0, 'order_add', $macchiato->id, 2);
    }
    // The previous 10 days: half the views.
    for ($i = 10; $i < 20; $i++) {
        Stats::hit($tenant->id, $today->copy()->subDays($i)->toDateString(), 0, 'menu_view', 0, 5);
    }

    return [$macchiato, $toast, $code, $window];
}

function csvRows(string $body): array
{
    expect(str_starts_with($body, "\xEF\xBB\xBF"))->toBeTrue();

    return array_map(fn ($l) => str_getcsv($l, escape: ''), array_values(array_filter(explode("\n", substr($body, 3)))));
}

it('exports daily numbers, QR codes and dishes as CSV for the chosen period', function () {
    [$owner, $tenant] = member(Role::Owner);
    insightsData($tenant);
    $today = now($tenant->timezone)->toDateString();
    $from = now($tenant->timezone)->subDays(6)->toDateString();

    $daily = csvRows($this->actingAs($owner)->get(t($tenant, 'insights/export.csv?table=daily&days=7'))
        ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent());
    expect($daily[0])->toBe(['Date', 'Menu views', 'QR scans', 'Dish views', '“My order” adds'])
        ->and($daily)->toHaveCount(8)
        ->and($daily[1])->toBe([$from, '10', '5', '4', '2'])
        ->and($daily[7][0])->toBe($today);

    $qr = csvRows($this->actingAs($owner)->get(t($tenant, 'insights/export.csv?table=qr&days=30'))->getContent());
    expect($qr[0])->toBe(['QR code', 'Kind', 'Branch', 'QR scans'])
        ->and($qr[1])->toBe(['Whole restaurant', 'Restaurant', 'Main', '40'])
        ->and($qr[2])->toBe(['Window poster', 'Extra', 'Main', '10']);

    // Dishes: most viewed first; a name starting with "=" can never run as a formula.
    $dishes = csvRows($this->actingAs($owner)->get(t($tenant, 'insights/export.csv?table=dishes&from='.$from.'&to='.$today))->getContent());
    expect($dishes)->toBe([
        ['Dish', 'Category', 'Dish views', '“My order” adds'],
        ['Macchiato', 'Coffee', '21', '14'],
        ["'=Avocado toast", 'Coffee', '7', '0'],
    ]);

    $this->actingAs($owner)->getJson(t($tenant, 'insights/export.csv?table=orders'))->assertInvalid('table');
    $this->actingAs($owner)->getJson(t($tenant, 'insights/export.csv?table=daily&from=2026-01-10&to=2026-01-01'))->assertInvalid('to');
});

it('makes a PDF report with summary, change against the previous period, chart, top dishes and top codes', function () {
    [$owner, $tenant] = member(Role::Owner);
    insightsData($tenant);

    $res = $this->actingAs($owner)->get(t($tenant, 'insights/report.pdf?days=7'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($res->headers->get('Content-Disposition'))->toContain(Str::slug($tenant->name.' insights'));
    $pdf = $res->getContent();
    expect($pdf)->toContain('/MediaBox [0 0 595.28 841.89]')
        ->toContain('(Insights report')
        ->toContain('(70)') // menu views in 7 days
        ->toContain('(Top dishes)')
        ->toContain('(Macchiato)')
        ->toContain('(Top QR codes)')
        ->toContain('(Window poster)')
        ->toContain('(+40 % vs previous period)'); // 70 views against 50 the week before

    // 10 days against the 10 before: views doubled.
    $from = now($tenant->timezone)->subDays(9)->toDateString();
    $pdf = $this->actingAs($owner)->get(t($tenant, 'insights/report.pdf?from='.$from.'&to='.now($tenant->timezone)->toDateString()))->getContent();
    expect($pdf)->toContain('(+100 % vs previous period)')->toContain('(100)');
});

it('is for owners and managers on plans with Insights', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$manager] = member(Role::Manager, $tenant);
    [$staff] = member(Role::Staff, $tenant);

    $this->actingAs($manager)->get(t($tenant, 'insights/export.csv?table=daily'))->assertOk();
    $this->actingAs($staff)->get(t($tenant, 'insights/export.csv?table=daily'))->assertForbidden();
    $this->actingAs($staff)->get(t($tenant, 'insights/report.pdf'))->assertForbidden();

    $plan = Plan::create(['name' => ['en' => 'Basic'], 'slug' => 'basic', 'limits' => []]);
    DB::table('plan_features')->insert(['plan_id' => $plan->id, 'feature_key' => 'insights', 'enabled' => false]);
    $tenant->forceFill(['plan_id' => $plan->id])->save();
    Features::forget();
    $this->actingAs($owner)->getJson(t($tenant, 'insights/report.pdf'))->assertStatus(402);
    $this->actingAs($owner)->getJson(t($tenant, 'insights/export.csv?table=daily'))->assertStatus(402);
});

it('e-mails last month\'s report to the owners on the 1st, once, when switched on', function () {
    Notification::fake();
    [$owner, $tenant] = member(Role::Owner);
    [$manager] = member(Role::Manager, $tenant);
    $tenant->forceFill(['timezone' => 'Africa/Addis_Ababa'])->save();
    $this->travelTo(CarbonImmutable::parse('2026-11-14 10:00', 'Africa/Addis_Ababa'));

    $this->actingAs($owner)->getJson(t($tenant, 'insights/settings'))->assertJsonPath('data.monthly_email', false);
    $this->actingAs($manager)->putJson(t($tenant, 'insights/settings'), ['monthly_email' => true], idem())->assertJsonPath('data.monthly_email', true);
    expect($tenant->fresh()->insights_report_sent)->toBe('2026-10'); // October is not sent mid-November

    $this->artisan('insights:monthly')->assertSuccessful()->run();
    Notification::assertNothingSent();

    // 1 December, 06:30 local: too early; 07:00: November's report goes to the owner only.
    $this->travelTo(CarbonImmutable::parse('2026-12-01 06:30', 'Africa/Addis_Ababa'));
    $this->artisan('insights:monthly')->run();
    Notification::assertNothingSent();
    $this->travelTo(CarbonImmutable::parse('2026-12-01 07:00', 'Africa/Addis_Ababa'));
    $this->artisan('insights:monthly')->run();
    $this->artisan('insights:monthly')->run(); // the next hour: not again
    Notification::assertSentToTimes($owner, MonthlyInsightsNotification::class, 1);
    Notification::assertNotSentTo($manager, MonthlyInsightsNotification::class);
    Notification::assertSentTo($owner, MonthlyInsightsNotification::class, fn ($n) => $n->month === '2026-11' && $n->tenantId === $tenant->id);
    expect($tenant->fresh()->insights_report_sent)->toBe('2026-11');

    // The mail itself: subject, numbers and the PDF for November.
    Stats::hit($tenant->id, '2026-11-20', 0, 'menu_view', 0, 42);
    $mail = (new MonthlyInsightsNotification($tenant->id, '2026-11'))->toMail($owner);
    expect($mail->subject)->toBe($tenant->name.': your menu in November 2026')
        ->and(implode(' ', $mail->introLines))->toContain('42 menu views')
        ->and($mail->rawAttachments[0]['name'])->toBe(Str::slug($tenant->name.' insights 2026-11-01 2026-11-30').'.pdf')
        ->and($mail->rawAttachments[0]['data'])->toContain('1 Nov 2026')->toContain('30 Nov 2026');

    // Switched off: nothing.
    $this->travelTo(CarbonImmutable::parse('2027-01-01 08:00', 'Africa/Addis_Ababa'));
    $tenant->fresh()->forceFill(['insights_report' => false])->save();
    $this->artisan('insights:monthly')->run();
    Notification::assertSentToTimes($owner, MonthlyInsightsNotification::class, 1);
});
