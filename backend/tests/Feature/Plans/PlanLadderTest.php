<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Features;
use App\Support\Legal\LegalText;
use App\Support\PlanLimits;
use App\Support\Plans\PlanLadder;
use App\Support\PlatformSettings;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

function onPlan(string $slug): array
{
    [$owner, $tenant] = member(Role::Owner);
    $tenant->forceFill(['plan_id' => Plan::where('slug', $slug)->value('id')])->save();
    Features::forget();

    return [$owner, $tenant->fresh()];
}

function limitOf(Tenant $tenant, string $key): ?int
{
    return asTenant($tenant, fn () => app(PlanLimits::class)->limit($key));
}

it('has four public plans: Free, Plus, Pro and Business, in that order', function () {
    expect(Plan::where('is_public', true)->orderBy('sort')->pluck('slug')->all())->toBe(['free', 'plus', 'pro', 'business']);
    expect(Plan::where('slug', 'plus')->first())->price_minor->toBe(49_000)->price_year_minor->toBe(490_000);
    expect(Plan::where('slug', 'free')->first())->price_minor->toBe(0);
});

it('gives each plan its limits and features', function () {
    $expect = [
        'free' => [['photos' => 100, 'branches' => 1], ['style_grid', 'style_minimal', 'pdf_menu', 'order_list', 'import_export'], ['promotions', 'insights', 'price_periods', 'remove_branding', 'branches', 'style_editorial', 'style_bistro']],
        'plus' => [['photos' => 500, 'branches' => 1], ['style_grid', 'style_minimal', 'style_editorial', 'style_bistro', 'promotions', 'insights', 'import_export'], ['price_periods', 'remove_branding', 'branches']],
        'pro' => [['photos' => 2000, 'branches' => 3], array_keys(Features::DEFAULTS), []],
        'business' => [['photos' => null, 'branches' => null], array_keys(Features::DEFAULTS), []],
    ];
    foreach ($expect as $slug => [$limits, $on, $off]) {
        [, $tenant] = onPlan($slug);
        foreach ($limits as $key => $n) {
            expect(limitOf($tenant, $key))->toBe($n, "$slug $key");
        }
        foreach (PlanLadder::UNLIMITED as $key) {
            expect(limitOf($tenant, $key))->toBeNull("$slug $key");
        }
        $features = Features::for($tenant);
        foreach ($on as $key) {
            expect($features[$key])->toBeTrue("$slug $key");
        }
        foreach ($off as $key) {
            expect($features[$key])->toBeFalse("$slug $key");
        }
    }
});

it('really is unlimited on Free: menus, dishes and tables', function () {
    [$owner, $tenant] = onPlan('free');
    [$menu, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($menu) {
        foreach (range(1, 120) as $n) {
            DB::table('items')->insert(['tenant_id' => Menu::find($menu->id)->tenant_id, 'category_id' => Category::first()->id, 'name' => json_encode(['en' => "Dish $n"]), 'price_minor' => 1000, 'sort' => $n, 'created_at' => now(), 'updated_at' => now()]);
        }
    });
    expect(asTenant($tenant, fn () => Item::count()))->toBeGreaterThan(100);

    $this->actingAs($owner)->postJson(t($tenant, 'items'), ['category_id' => $category->id, 'name' => ['en' => 'One more']], idem())->assertCreated();
    foreach (range(1, 6) as $n) {
        $this->actingAs($owner)->postJson(t($tenant, 'menus'), ['name' => ['en' => "Menu $n"]], idem())->assertCreated();
    }
    $main = asTenant($tenant, fn () => Branch::first());
    $this->actingAs($owner)->postJson(t($tenant, "branches/{$main->id}/tables"), ['prefix' => 'T', 'from' => 1, 'to' => 60], idem())->assertSuccessful();

    // Photos and branches are the only counts.
    expect(fn () => asTenant($tenant, fn () => app(PlanLimits::class)->ensureRoom('photos', 100)))->toThrow(HttpResponseException::class);
    expect(fn () => asTenant($tenant, fn () => app(PlanLimits::class)->ensureRoom('branches', 1)))->toThrow(HttpResponseException::class);
    asTenant($tenant, fn () => app(PlanLimits::class)->ensureRoom('photos', 99));
});

it('never takes anything away from a paying restaurant, and moves it once its paid time ends', function () {
    // An install with older plans: a Pro with 5 branches (paying), a Plus nobody pays for,
    // a Business without "remove branding", a customised Free and an extra public plan.
    $free = Plan::where('slug', 'free')->first();
    DB::table('plan_features')->where('plan_id', '!=', $free->id)->delete();
    Plan::whereIn('slug', ['plus', 'pro', 'business'])->delete();
    $free->forceFill(['limits' => ['photos' => 500, 'items' => 30]])->save();
    $pro = Plan::create(['name' => ['en' => 'Pro'], 'slug' => 'pro', 'price_minor' => 120_000, 'currency' => 'ETB', 'interval' => 'month', 'limits' => ['branches' => 5, 'items' => 300]]);
    $plus = Plan::create(['name' => ['en' => 'Plus'], 'slug' => 'plus', 'price_minor' => 40_000, 'currency' => 'ETB', 'interval' => 'month', 'limits' => ['photos' => 900]]);
    $business = Plan::create(['name' => ['en' => 'Business'], 'slug' => 'business', 'price_minor' => 300_000, 'currency' => 'ETB', 'interval' => 'month', 'limits' => []]);
    $starter = Plan::create(['name' => ['en' => 'Starter'], 'slug' => 'starter', 'price_minor' => 50_000, 'currency' => 'ETB', 'interval' => 'month', 'limits' => ['items' => 80], 'is_public' => true]);

    $paying = Tenant::factory()->create(['plan_id' => $pro->id, 'paid_until' => now()->addDays(20)]);
    $onPlus = Tenant::factory()->create(['plan_id' => $plus->id, 'paid_until' => now()->subMonths(3), 'trial_ends_at' => null]);
    $onBusiness = Tenant::factory()->create(['plan_id' => $business->id, 'paid_until' => now()->addMonth()]);
    $onStarter = Tenant::factory()->create(['plan_id' => $starter->id, 'paid_until' => now()->addMonth()]);
    $onFree = Tenant::factory()->create(['plan_id' => $free->id]);
    Features::forget();
    $before = Features::for($paying);

    PlanLadder::apply();
    PlanLadder::apply(); // safe twice
    Features::forget();

    // Paying Pro: still on the very same plan row, unchanged, now hidden and pointing at the new Pro.
    $paying->refresh();
    $old = Plan::find($pro->id);
    expect($paying->plan_id)->toBe($pro->id)
        ->and($old->slug)->toBe('pro-legacy')
        ->and($old->name['en'])->toBe('Pro (old)')
        ->and($old->is_public)->toBeFalse()
        ->and($old->limits)->toBe(['branches' => 5, 'items' => 300])
        ->and($old->price_minor)->toBe(120_000)
        ->and(Features::for($paying))->toBe($before);
    $newPro = Plan::where('slug', 'pro')->first();
    expect($old->replaced_by_plan_id)->toBe($newPro->id)
        ->and($newPro->limits)->toBe(['photos' => 2000, 'branches' => 3])
        ->and(Plan::where('slug', 'like', 'pro%')->count())->toBe(2);

    // Plus nobody pays for: updated in place, price kept, yearly price added.
    $plus->refresh();
    expect($plus->limits)->toBe(['photos' => 500, 'branches' => 1])->and($plus->price_minor)->toBe(40_000)->and($plus->price_year_minor)->toBe(400_000);
    // Business only gains (remove branding): updated in place, paying restaurant keeps its plan.
    expect($onBusiness->fresh()->plan_id)->toBe($business->id)->and(Features::for($onBusiness->fresh())['remove_branding'])->toBeTrue();
    // Free: only better (customised 500 photos kept, dishes unlimited; it had no branch count, extra branches stay switched off).
    expect($free->fresh()->limits)->toBe(['photos' => 500])
        ->and(Features::for($onFree->fresh())['branches'])->toBeFalse()
        ->and(limitOf($onFree, 'items'))->toBeNull();
    // Other plans: hidden from the website, untouched for their restaurants.
    expect($starter->fresh())->is_public->toBeFalse()->limits->toBe(['items' => 80])
        ->and($onStarter->fresh()->plan_id)->toBe($starter->id);

    // While paid: no move. After the paid time: moved to the new Pro, logged.
    $this->artisan('plans:move-replaced')->assertSuccessful();
    expect($paying->fresh()->plan_id)->toBe($pro->id);
    $this->travelTo(now()->addDays(21));
    $this->artisan('plans:move-replaced')->assertSuccessful();
    expect($paying->fresh()->plan_id)->toBe($newPro->id)
        ->and(DB::table('activity_log')->where('action', 'platform.plan_moved')->count())->toBe(1);
});

it('lets the Super Admin edit the yearly price and sees what replaced a plan', function () {
    $admin = superAdmin();
    $plus = Plan::where('slug', 'plus')->first();
    $this->actingAs($admin)->patchJson(p("plans/{$plus->id}"), ['price_year_minor' => 450_000, 'limits' => ['photos' => 800, 'branches' => 1]], idem())->assertOk();
    expect($plus->fresh())->price_year_minor->toBe(450_000)->limits->toBe(['photos' => 800, 'branches' => 1]);
    $row = collect($this->actingAs($admin)->getJson(p('plans'))->assertOk()->json('data'))->firstWhere('slug', 'plus');
    expect($row['price_year_minor'])->toBe(450_000)->and($row)->toHaveKey('replaced_by_plan_id');
});

it('updates the Terms to the new plan wording only when they were not edited', function () {
    $run = fn () => (require database_path('migrations/2026_11_14_500002_update_terms_default.php'))->up();
    $old = file_get_contents(database_path('legal-history/terms-2026-11-13-v1.md'));
    expect($old)->toContain('It has limits (for example on dishes, photos and tables)')
        ->and(LegalText::default('terms'))->toContain('with unlimited dishes, menus and QR codes');

    PlatformSettings::save(['terms' => $old]);
    $run();
    expect(PlatformSettings::get('terms'))->toBe(LegalText::default('terms'));

    PlatformSettings::save(['terms' => $old.' Edited.']);
    $run();
    expect(PlatformSettings::get('terms'))->toEndWith('Edited.');
});
