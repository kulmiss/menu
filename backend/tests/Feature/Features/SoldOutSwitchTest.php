<?php

use App\Enums\Role;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Branch;
use App\Models\BranchItemOverride;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Features;
use Illuminate\Support\Facades\DB;

/** "Sold out" as a feature switch (owner, 2026-11-14). */
function soldOutOff(Tenant $tenant, string $how = 'restaurant'): void
{
    if ($how === 'plan') {
        $plan = Plan::create(['name' => ['en' => 'No sold out'], 'slug' => 'no-sold-out', 'price_minor' => 0, 'interval' => 'month', 'limits' => []]);
        DB::table('plan_features')->insert(['plan_id' => $plan->id, 'feature_key' => 'sold_out', 'enabled' => false]);
        $tenant->forceFill(['plan_id' => $plan->id])->save();
    } else {
        DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'sold_out', 'enabled' => false]);
    }
    Features::forget();
    $tenant->bumpCacheVersion();
}

it('is on by default and listed with the other switches', function () {
    [$owner, $tenant] = member(Role::Owner);
    expect(Features::enabled($tenant, 'sold_out'))->toBeTrue();
    $this->actingAs($owner)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.tenants.0.features.sold_out', true);
});

it('hides the marks on the guest menu while off, and keeps them for later', function (string $how) {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    asTenant($tenant, fn () => $item->forceFill(['is_sold_out' => true])->save());
    expect($this->get('/'.$tenant->slug)->assertOk()->getContent())->toContain('class="bd x0"');

    soldOutOff($tenant, $how);
    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();
    expect($html)->not->toContain('class="bd x0"')->not->toContain('@class')
        ->and(asTenant($tenant, fn () => Item::find($item->id)->is_sold_out))->toBeTrue(); // kept

    DB::table('tenant_feature_overrides')->where('tenant_id', $tenant->id)->delete();
    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'sold_out', 'enabled' => true]);
    Features::forget();
    $tenant->bumpCacheVersion();
    expect($this->get('/'.$tenant->slug)->getContent())->toContain('class="bd x0"'); // back
})->with(['restaurant', 'plan']);

it('refuses the sold-out switches and ignores the field while off', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    $option = asTenant($tenant, function () {
        $g = AddonGroup::create(['name' => ['en' => 'Milk'], 'min_choices' => 0, 'max_choices' => 1]);

        return AddonOption::create(['addon_group_id' => $g->id, 'name' => ['en' => 'Oat'], 'price_minor' => 0, 'is_sold_out' => true]);
    });
    soldOutOff($tenant);
    $this->actingAs($owner);

    $this->patchJson(t($tenant, "items/{$item->id}/sold-out"), ['is_sold_out' => true], idem())->assertStatus(402)->assertJsonPath('code', 'features.sold_out');
    $this->patchJson(t($tenant, "addon-options/{$option->id}/sold-out"), ['is_sold_out' => false], idem())->assertStatus(402);
    $this->postJson(t($tenant, 'items/bulk'), ['ids' => [$item->id], 'action' => 'sold_out'], idem())->assertStatus(402);
    $this->patchJson(t($tenant, "items/{$item->id}"), ['is_sold_out' => true, 'name' => ['en' => 'Flat white']], idem())->assertOk()
        ->assertJsonPath('data.is_sold_out', false);
    expect(asTenant($tenant, fn () => Item::find($item->id)->is_sold_out))->toBeFalse();

    // The add-on group screen saves its options: the hidden mark on "Oat" is kept.
    $this->patchJson(t($tenant, "addon-groups/{$option->addon_group_id}"), ['name' => ['en' => 'Milk'], 'min_choices' => 0, 'max_choices' => 1,
        'options' => [['id' => $option->id, 'name' => ['en' => 'Oat'], 'price_minor' => 0, 'is_sold_out' => false]]], idem())->assertOk()
        ->assertJsonPath('data.options.0.is_sold_out', false);
    expect(asTenant($tenant, fn () => AddonOption::find($option->id)->is_sold_out))->toBeTrue();

    // Dashboard list empty, dish table filter and flag ignore the marks.
    asTenant($tenant, fn () => $item->forceFill(['is_sold_out' => true])->save());
    $this->getJson(t($tenant, 'dashboard'))->assertOk()->assertJsonPath('data.sold_out', []);
    $this->getJson(t($tenant, 'items?page=1&status=sold_out'))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(t($tenant, 'items?page=1'))->assertOk()->assertJsonPath('data.0.is_sold_out', false);
});

it('keeps branch sold-out marks when the branch screen saves while off', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    $branch = asTenant($tenant, fn () => Branch::where('is_default', true)->first() ?? Branch::create(['name' => 'Main', 'slug' => 'main', 'is_default' => true]));
    asTenant($tenant, fn () => BranchItemOverride::create(['branch_id' => $branch->id, 'item_id' => $item->id, 'is_sold_out' => true]));
    soldOutOff($tenant);
    $this->actingAs($owner);

    $this->getJson(t($tenant, "branches/{$branch->id}/overrides"))->assertOk()->assertJsonPath('data.0.is_sold_out', null);
    $this->putJson(t($tenant, "branches/{$branch->id}/overrides"), ['overrides' => [['item_id' => $item->id, 'price_minor' => 9000, 'is_sold_out' => null, 'is_hidden' => null]]], idem())->assertOk();
    $o = asTenant($tenant, fn () => BranchItemOverride::where('branch_id', $branch->id)->first());
    expect($o->is_sold_out)->toBeTrue()->and($o->price_minor)->toBe(9000);
});

it('lets the Super Admin switch it per restaurant', function () {
    [, $tenant] = member(Role::Owner);
    $this->actingAs(superAdmin())->putJson(p("restaurants/{$tenant->id}/features"), ['features' => ['sold_out' => false]], idem())->assertOk();
    Features::forget();
    expect(Features::enabled($tenant->fresh(), 'sold_out'))->toBeFalse();
});
