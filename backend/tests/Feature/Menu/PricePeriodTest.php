<?php

use App\Enums\Role;
use App\Models\ItemVariant;
use App\Models\Plan;
use App\Models\PricePeriod;
use App\Support\Features;
use App\Support\PublicMenu\MenuPageData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Time-based prices (owner, 2026-11-14). Restaurant time zone: Addis Ababa (UTC+3). */
function happyHour(array $over = []): array
{
    return ['name' => ['en' => 'Happy hour'], 'weekdays' => [1, 2, 3, 4, 5], 'starts_at' => '16:00', 'ends_at' => '18:00', ...$over];
}

beforeEach(function () {
    [$this->owner, $this->tenant] = member(Role::Owner);
    $this->tenant->update(['timezone' => 'Africa/Addis_Ababa']);
    [, , $this->item] = sampleMenu($this->tenant);
    $this->sized = asTenant($this->tenant, function () {
        $item = $this->item->replicate()->fill(['name' => ['en' => 'Shiro'], 'price_minor' => null]);
        $item->save();
        ItemVariant::create(['item_id' => $item->id, 'name' => ['en' => 'Small'], 'price_minor' => 8000]);
        ItemVariant::create(['item_id' => $item->id, 'name' => ['en' => 'Large'], 'price_minor' => 15000]);

        return $item->load('variants');
    });
});

it('knows when a window is running, also across midnight', function () {
    $p = new PricePeriod(['weekdays' => [5], 'starts_at' => '22:00:00', 'ends_at' => '02:00:00']); // Friday night
    $tz = 'Africa/Addis_Ababa';
    expect($p->endsAfter(Carbon::parse('2026-11-13 23:30', $tz))?->format('D H:i'))->toBe('Sat 02:00') // Friday 23:30
        ->and($p->endsAfter(Carbon::parse('2026-11-14 01:59', $tz))?->format('D H:i'))->toBe('Sat 02:00') // Saturday morning
        ->and($p->endsAfter(Carbon::parse('2026-11-14 02:00', $tz)))->toBeNull()
        ->and($p->endsAfter(Carbon::parse('2026-11-12 23:30', $tz)))->toBeNull(); // Thursday
    $day = new PricePeriod(['weekdays' => [1, 2, 3, 4, 5], 'starts_at' => '16:00:00', 'ends_at' => '18:00:00']);
    expect($day->endsAfter(Carbon::parse('2026-11-13 16:00', $tz))?->format('H:i'))->toBe('18:00')
        ->and($day->endsAfter(Carbon::parse('2026-11-13 18:00', $tz)))->toBeNull()
        ->and($day->endsAfter(Carbon::parse('2026-11-14 17:00', $tz)))->toBeNull(); // Saturday
});

it('lets owners and price managers save periods; staff and the Free plan cannot', function () {
    $this->actingAs($this->owner);
    $body = happyHour(['items' => [
        ['item_id' => $this->item->id, 'mode' => 'fixed', 'value' => 9000],
        ['item_id' => $this->sized->id, 'item_variant_id' => $this->sized->variants[1]->id, 'mode' => 'percent', 'value' => 20],
    ]]);
    $id = $this->postJson(t($this->tenant, 'price-periods'), $body, idem())->assertCreated()
        ->assertJsonPath('data.weekdays', [1, 2, 3, 4, 5])->assertJsonCount(2, 'data.items')->json('data.id');
    $this->postJson(t($this->tenant, 'price-periods'), happyHour(['items' => [['item_id' => $this->item->id, 'mode' => 'percent', 'value' => 95]]]), idem())
        ->assertUnprocessable()->assertJsonValidationErrors('items.0.value');
    $this->postJson(t($this->tenant, 'price-periods'), happyHour(['items' => [['item_id' => $this->item->id, 'item_variant_id' => $this->sized->variants[0]->id, 'mode' => 'fixed', 'value' => 1]]]), idem())
        ->assertUnprocessable();
    $this->putJson(t($this->tenant, "price-periods/{$id}"), happyHour(['starts_at' => '15:00', 'items' => [['item_id' => $this->item->id, 'mode' => 'fixed', 'value' => 9500]]]), idem())
        ->assertOk()->assertJsonPath('data.starts_at', '15:00')->assertJsonCount(1, 'data.items');

    [$staff] = member(Role::Staff, $this->tenant);
    app('auth')->forgetGuards();
    $this->actingAs($staff)->getJson(t($this->tenant, 'price-periods'))->assertForbidden();

    $free = Plan::where('slug', 'free')->sole();
    $this->tenant->update(['plan_id' => $free->id]);
    Features::forget();
    app('auth')->forgetGuards();
    $this->actingAs($this->owner)->getJson(t($this->tenant, 'price-periods'))->assertStatus(402)->assertJsonPath('code', 'features.price_periods');
});

it('shows the special price, the crossed-out normal price and the label on the guest page only during the period', function () {
    asTenant($this->tenant, function () {
        $p = PricePeriod::create(happyHour());
        $p->items()->create(['item_id' => $this->item->id, 'mode' => 'fixed', 'value' => 9000]);
        $p->items()->create(['item_id' => $this->sized->id, 'item_variant_id' => null, 'mode' => 'percent', 'value' => 20]);
    });

    $this->travelTo(Carbon::parse('2026-11-13 16:30', 'Africa/Addis_Ababa')); // Friday
    $html = $this->get('/'.$this->tenant->slug)->assertOk()->getContent();
    expect($html)->toContain('<s>Br 120</s> Br 90')
        ->toContain('Happy hour until 18:00')
        ->toContain('<s>from Br 80</s> from Br 64'); // 20% off every size
    $page = app(MenuPageData::class)->build($this->tenant->fresh());
    $row = collect($page['menus'][0]['categories'][0]['items'])->keyBy('id');
    expect($row[$this->item->id]['cart'][0])->toBe(9000) // "My order" uses the price valid now
        ->and($row[$this->sized->id]['cart'][1])->toBe([[$this->sized->variants[0]->id, 6400], [$this->sized->variants[1]->id, 12000]])
        ->and($row[$this->sized->id]['sheet']['v'][1][1])->toBe('Br 120 · was Br 150');

    // The printable PDF menu keeps the normal prices.
    $pdf = app(MenuPageData::class)->build($this->tenant->fresh(), periods: false);
    expect(collect($pdf['menus'][0]['categories'][0]['items'])->firstWhere('id', $this->item->id)['price'])->toBe('Br 120');

    $this->travelTo(Carbon::parse('2026-11-13 18:01', 'Africa/Addis_Ababa'));
    $html = $this->get('/'.$this->tenant->slug)->assertOk()->getContent();
    expect($html)->not->toContain('Happy hour until')->toContain('Br 120');
});

it('rebuilds the guest page when a period starts and ends (scheduler)', function () {
    asTenant($this->tenant, function () {
        PricePeriod::create(happyHour())->items()->create(['item_id' => $this->item->id, 'mode' => 'fixed', 'value' => 9000]);
    });
    $version = fn () => (int) DB::table('tenants')->where('id', $this->tenant->id)->value('cache_version');

    $this->travelTo(Carbon::parse('2026-11-13 15:59', 'Africa/Addis_Ababa'));
    $this->artisan('menu:price-periods')->assertSuccessful();
    $before = $version();
    $this->artisan('menu:price-periods');
    expect($version())->toBe($before); // nothing changed, nothing rebuilt

    $this->travelTo(Carbon::parse('2026-11-13 16:00', 'Africa/Addis_Ababa'));
    $this->artisan('menu:price-periods');
    expect($version())->toBe($before + 1);

    $this->travelTo(Carbon::parse('2026-11-13 18:00', 'Africa/Addis_Ababa'));
    $this->artisan('menu:price-periods');
    expect($version())->toBe($before + 2);
});

it('previews what guests see now', function () {
    asTenant($this->tenant, function () {
        PricePeriod::create(happyHour())->items()->create(['item_id' => $this->item->id, 'mode' => 'percent', 'value' => 25]);
    });
    $this->travelTo(Carbon::parse('2026-11-13 17:00', 'Africa/Addis_Ababa'));
    $this->actingAs($this->owner)->getJson(t($this->tenant, 'price-periods/preview'))->assertOk()
        ->assertJsonPath('data.running.0.label', 'Happy hour until 18:00')
        ->assertJsonPath('data.prices.0', ['item' => 'Macchiato', 'normal' => 'Br 120', 'now' => 'Br 90']);
});
