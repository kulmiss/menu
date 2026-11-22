<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\TenantAppearance;
use App\Support\Features;
use App\Support\Help\HelpLibrary;
use App\Support\PlatformSettings;
use App\Support\Qr\QrMap;
use Illuminate\Support\Facades\DB;

/*
 * Tables can be switched off (owner, 2026-11-22: some owners found them confusing): for
 * everyone (Settings → Features), per plan, or per restaurant. Off: no Tables in the admin
 * API, no table cards, no table codes in the QR list, no table name on "Show to waiter";
 * codes already printed on tables keep opening the menu.
 */

/** A restaurant with a menu, "My order" on, and tables T 1–T 2 on its main branch. */
function tablesRestaurant(): array
{
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    asTenant($tenant, fn () => TenantAppearance::create(TenantAppearance::DEFAULTS));
    $main = asTenant($tenant, fn () => Branch::where('is_default', true)->firstOrFail());
    test()->actingAs($owner)->postJson(t($tenant, "branches/{$main->id}/tables"), ['prefix' => 'T', 'from' => 1, 'to' => 2], idem())->assertCreated();
    $code = asTenant($tenant, fn () => QrCode::whereNotNull('dining_table_id')->orderBy('id')->value('code'));

    return [$owner, $tenant->fresh(), $main, $code];
}

it('is on by default, as before', function () {
    [$owner, $tenant, $main, $code] = tablesRestaurant();
    expect(Features::DEFAULTS['tables'])->toBeTrue();
    $this->actingAs($owner)->getJson(t($tenant, "branches/{$main->id}/tables"))->assertOk()->assertJsonCount(2, 'data');
    expect(collect($this->actingAs($owner)->getJson(t($tenant, 'qr-codes'))->json('data'))->where('kind', 'table'))->toHaveCount(2);
    // "Show to waiter" knows the table from the scanned code.
    $this->app['auth']->forgetGuards();
    expect($this->get('/'.$tenant->slug)->assertOk()->getContent())->toContain('"'.$code.'":"T 1"');
});

it('switched off for everyone: no tables in the admin, no table name for guests, printed codes still work', function () {
    [$owner, $tenant, $main, $code] = tablesRestaurant();
    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['tables' => false]], idem())->assertOk();
    Features::forget();

    $this->actingAs($owner);
    $this->getJson(t($tenant, "branches/{$main->id}/tables"))->assertStatus(402)->assertJsonPath('code', 'features.tables');
    $this->postJson(t($tenant, "branches/{$main->id}/tables"), ['prefix' => 'T', 'from' => 3, 'to' => 4], idem())->assertStatus(402);
    $table = asTenant($tenant, fn () => DB::table('dining_tables')->value('id'));
    $this->deleteJson(t($tenant, "tables/{$table}"), [], idem())->assertStatus(402);
    $this->get(t($tenant, "branches/{$main->id}/table-cards.pdf"))->assertStatus(402);
    // The QR list keeps the restaurant's own code, without the table codes.
    $kinds = collect($this->getJson(t($tenant, 'qr-codes'))->assertOk()->json('data'))->pluck('kind')->all();
    expect($kinds)->toContain('restaurant')->not->toContain('table');
    // Nothing was deleted: both tables and their codes are still there.
    expect(asTenant($tenant, fn () => DB::table('dining_tables')->count()))->toBe(2);

    // Guests: no table name, but the printed code still opens the menu.
    $this->app['auth']->forgetGuards();
    expect($this->get('/'.$tenant->slug)->assertOk()->getContent())->not->toContain('"'.$code.'":"T 1"');
    expect(QrMap::entries()[$code])->toBe('/'.$tenant->slug.'#q='.$code);
    $this->get('/q/'.$code)->assertRedirect();

    // Owners don't see the help article about tables.
    expect(collect(HelpLibrary::visible('restaurant', $tenant, 'owner'))->pluck('slug'))->not->toContain('tables-and-branch-qr');

    // On again: everything is back, as it was.
    $this->actingAs(superAdmin())->putJson(p('settings/features'), ['features' => ['tables' => true]], idem())->assertOk();
    Features::forget();
    $this->actingAs($owner)->getJson(t($tenant, "branches/{$main->id}/tables"))->assertOk()->assertJsonCount(2, 'data');
});

it('can be switched off for one plan or one restaurant only', function () {
    [$owner, $tenant, $main] = tablesRestaurant();
    $plan = Plan::create(['name' => ['en' => 'No tables'], 'slug' => 'no-tables', 'limits' => []]);
    DB::table('plan_features')->insert(['plan_id' => $plan->id, 'feature_key' => 'tables', 'enabled' => false]);
    $tenant->forceFill(['plan_id' => $plan->id])->save();
    Features::forget();
    $this->actingAs($owner)->getJson(t($tenant, "branches/{$main->id}/tables"))->assertStatus(402);

    // The restaurant's own switch wins over its plan.
    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'tables', 'enabled' => true]);
    Features::forget();
    $this->actingAs($owner)->getJson(t($tenant, "branches/{$main->id}/tables"))->assertOk();
    expect(PlatformSettings::get('features_global'))->not->toHaveKey('tables');
});
