<?php

use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Branch;
use App\Models\BranchItemOverride;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Features;
use App\Support\PublicMenu\InlineAssets;
use Illuminate\Support\Facades\DB;

/** The page's embedded data. */
function pageData(string $html): array
{
    preg_match('#<script type="application/json" id="d">(.*?)</script>#s', $html, $m);

    return json_decode($m[1], true);
}

it('gives every orderable dish a + and the numbers the list needs', function () {
    $tenant = Tenant::factory()->create(['currency_code' => 'ETB']);
    [, $category, $macchiato] = sampleMenu($tenant);
    [$buna, $pot, $honey, $table] = asTenant($tenant, function () use ($category) {
        $buna = Item::create(['category_id' => $category->id, 'name' => ['en' => 'Buna'], 'sort' => 2]);
        ItemVariant::create(['item_id' => $buna->id, 'name' => ['en' => 'Cup'], 'price_minor' => 6000, 'sort' => 0]);
        $pot = ItemVariant::create(['item_id' => $buna->id, 'name' => ['en' => 'Pot'], 'price_minor' => 20000, 'sort' => 1]);
        $group = AddonGroup::create(['name' => ['en' => 'Extras'], 'min_choices' => 0, 'max_choices' => 2]);
        $honey = AddonOption::create(['addon_group_id' => $group->id, 'name' => ['en' => 'Honey'], 'price_minor' => 1500, 'sort' => 0]);
        $buna->addonGroups()->attach($group->id, ['tenant_id' => $buna->tenant_id, 'sort' => 0]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Gone'], 'price_minor' => 100, 'is_sold_out' => true, 'sort' => 3]);
        $table = DiningTable::create(['branch_id' => Branch::where('is_default', true)->value('id'), 'label' => '5']);

        return [$buna, $pot, $honey, $table];
    });
    $code = asTenant($tenant, fn () => QrCode::create(['code' => QrCode::newCode(), 'dining_table_id' => $table->id, 'branch_id' => $table->branch_id])->code);

    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();
    $d = pageData($html);

    expect(substr_count($html, 'class="ad" data-x="A"'))->toBe(2)         // not on the sold-out dish
        ->and($d['o'][$macchiato->id])->toBe([12000, [], [], 0])
        ->and($d['o'][$buna->id][1][1])->toBe([$pot->id, 20000])
        ->and($d['o'][$buna->id][2][0])->toBe([0, 2, [[$honey->id, 1500, 0]]])
        ->and($d['cu'])->toBe('Br')
        ->and($d['q'])->toBe([$code => '5'])
        ->and($d['t']['o']['not_order'])->toBe('This is not an order. Show it to your waiter.');
});

it('uses the branch prices', function () {
    $tenant = Tenant::factory()->create();
    [, , $item] = sampleMenu($tenant);
    asTenant($tenant, function () use ($item) {
        $piassa = Branch::create(['slug' => 'piassa', 'name' => ['en' => 'Piassa'], 'sort' => 1]);
        BranchItemOverride::create(['branch_id' => $piassa->id, 'item_id' => $item->id, 'price_minor' => 13500]);
    });

    $d = pageData((string) $this->get('/'.$tenant->slug.'/piassa')->assertOk()->getContent());
    expect($d['o'][$item->id][0])->toBe(13500);
});

it('is off when the restaurant, the plan or hidden prices say so', function (string $how) {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS,
        'show_order_list' => $how !== 'appearance', 'show_prices' => $how !== 'prices']));
    if ($how === 'plan') {
        DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'order_list', 'enabled' => false]);
        Features::forget();
    }

    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();
    expect($html)->not->toContain('class="ad"')->and(pageData($html)['o'])->toBeNull();
})->with(['appearance', 'plan', 'prices']);

it('keeps the list code out of the inline script and the list on the phone only', function () {
    $file = (string) file_get_contents(public_path(ltrim(InlineAssets::sheetUrl(), '/')));

    expect(InlineAssets::js())->not->toContain('localStorage')->not->toContain('mC');
    expect($file)->toContain('w.mC={')->toContain('localStorage')->toContain("'mo:'+D.id+':'+D.b")
        // Only anonymous counts leave the phone: dish, quantity, list total.
        ->toContain('{t:D.id,b:D.b,c:[id,1,total()')->toContain('{t:D.id,b:D.b,w:total()');
    expect(strlen(InlineAssets::js()))->toBeLessThan(5 * 1024);
});

it('counts dishes added and lists shown, without counting them as views', function () {
    $tenant = Tenant::factory()->create();
    [, , $item] = sampleMenu($tenant);
    $other = Tenant::factory()->create();
    [, , $foreign] = sampleMenu($other);
    $beacon = fn (array $body) => $this->call('POST', '/api/v1/beacon', [], [], [], ['CONTENT_TYPE' => 'text/plain', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone)'], json_encode(['t' => $tenant->id, ...$body]))->assertNoContent();

    $beacon(['c' => [$item->id, 1, 12000]]);
    $beacon(['c' => [$item->id, 1, 24000]]);
    $beacon(['c' => [$foreign->id, 1, 100]]);
    $beacon(['w' => 24000]);
    $beacon(['w' => 12000]);

    $count = fn (string $metric, int $subject = 0) => (int) DB::table('stats_daily')->where('tenant_id', $tenant->id)->where('metric', $metric)->where('subject_id', $subject)->sum('count');
    expect($count('order_add', $item->id))->toBe(2)
        ->and($count('order_add', $foreign->id))->toBe(0)
        ->and($count('order_shown'))->toBe(2)
        ->and($count('order_value'))->toBe(36000)
        ->and($count('menu_view'))->toBe(0);
});

it('draws the quick-add button as a small circle in a 44 px tap area that never covers the text', function (string $style) {
    $css = InlineAssets::css($style);

    expect($css)->toContain('.ad{position:absolute;top:var(--adt,0);inset-inline-end:0;z-index:1;width:2.75rem;height:2.75rem') // 44 px tap area on the photo corner
        ->toContain('.ad:before{content:"";position:absolute;inset:.4375rem;border-radius:50%')                      // 30 px visible circle
        ->toContain('li>.it.ao.np{padding-inline-end:2.75rem}')                                                       // rows without a photo keep room for it
        ->not->toContain('.ad{inset-inline-end:.5rem;bottom:.5rem}');
})->with(['editorial', 'grid', 'bistro']);

it('says "Menu updated" from the on-demand file after the page reloaded itself, not from the inline script', function () {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    $html = (string) $this->get('/'.$tenant->slug)->assertOk()->getContent();
    preg_match('#<script type="application/json" id="d">(.*?)</script>#s', $html, $m);

    expect(json_decode($m[1], true)['t']['mu'])->toBe('Menu updated');
    expect(File::get(resource_path('public-menu/sheet.js')))->toContain('sessionStorage.mn = mu')->toContain("setAttribute('role', 'status')");
    expect(InlineAssets::js())->not->toContain('Menu updated')
        ->and(strlen(InlineAssets::js()))->toBeLessThanOrEqual(5 * 1024);
});
