<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\QrCode;
use App\Support\Stats;

it('reports views and scans per day, top dishes and scans per code', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $category, $item] = sampleMenu($tenant);
    $other = asTenant($tenant, fn () => Item::create(['category_id' => $category->id, 'name' => ['en' => 'Shai'], 'price_minor' => 4500]));
    $main = asTenant($tenant, fn () => Branch::firstOrFail());
    $table = asTenant($tenant, fn () => DiningTable::create(['branch_id' => $main->id, 'label' => '7']));
    $tableCode = asTenant($tenant, fn () => QrCode::create(['code' => QrCode::newCode(), 'branch_id' => $main->id, 'dining_table_id' => $table->id]));
    $today = now($tenant->timezone)->toDateString();
    $yesterday = now($tenant->timezone)->subDay()->toDateString();

    foreach ([[$today, 3], [$yesterday, 2]] as [$date, $n]) {
        for ($i = 0; $i < $n; $i++) {
            Stats::hit($tenant->id, $date, $main->id, 'menu_view');
        }
    }
    Stats::hit($tenant->id, $today, $main->id, 'qr_scan', $tableCode->id);
    Stats::hit($tenant->id, $today, $main->id, 'item_view', $other->id);
    Stats::hit($tenant->id, $today, $main->id, 'item_view', $other->id);
    Stats::hit($tenant->id, $today, $main->id, 'item_view', $item->id);
    Stats::hit($tenant->id, now()->subDays(60)->toDateString(), $main->id, 'menu_view'); // outside 30 days

    $res = $this->actingAs($owner)->getJson(t($tenant, 'insights?days=7'))->assertOk()
        ->assertJsonCount(7, 'data.days')
        ->assertJsonPath('data.days.6', ['date' => $today, 'views' => 3, 'scans' => 1])
        ->assertJsonPath('data.days.5.views', 2)
        ->assertJsonPath('data.totals', ['views' => 5, 'scans' => 1, 'item_views' => 3])
        ->assertJsonPath('data.top_items.0', ['id' => $other->id, 'name' => 'Shai', 'count' => 2])
        ->assertJsonPath('data.scans.0.label', 'Table 7');

    $this->actingAs($owner)->getJson(t($tenant, 'insights?days=30'))->assertJsonPath('data.totals.views', 5);
    $this->actingAs($owner)->getJson(t($tenant, 'insights?days=5'))->assertInvalid('days');
});

it('shows today on the dashboard with the sold-out list, also for staff', function () {
    [$staff, $tenant] = member(Role::Staff);
    [, , $item] = sampleMenu($tenant);
    asTenant($tenant, fn () => $item->update(['is_sold_out' => true]));
    Stats::hit($tenant->id, now($tenant->timezone)->toDateString(), 0, 'menu_view');

    $this->actingAs($staff)->getJson(t($tenant, 'dashboard'))->assertOk()
        ->assertJsonPath('data.today', ['views' => 1, 'scans' => 0])
        ->assertJsonPath('data.sold_out.0.id', $item->id);
    $this->actingAs($staff)->getJson(t($tenant, 'insights'))->assertForbidden();
});

it('never mixes restaurants', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, $other] = member(Role::Owner);
    Stats::hit($other->id, now($other->timezone)->toDateString(), 0, 'menu_view');

    $this->actingAs($owner)->getJson(t($tenant, 'insights'))->assertJsonPath('data.totals.views', 0);
    $this->actingAs($owner)->getJson(t($other, 'insights'))->assertNotFound();
});
