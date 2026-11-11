<?php

use App\Enums\Role;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Features;
use App\Support\PublicMenu\MenuPageCache;
use App\Support\PublicMenu\MenuPageData;
use App\Support\PublicMenu\PdfMenu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** The menu as uncompressed PDF text, for reading in tests. */
function pdfText(Tenant $tenant, array $options = []): string
{
    return asTenant($tenant, fn () => (new PdfMenu)->render(app(MenuPageData::class)->build($tenant), [...$options, 'compress' => false]));
}

it('prints dishes with prices, sizes, descriptions and tags, and leaves sold-out dishes out', function () {
    $tenant = Tenant::factory()->create(['name' => 'Buna & Bread']);
    [, $category, $macchiato] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category, $macchiato) {
        $macchiato->update(['summary' => ['en' => 'Espresso with a spoon of foam']]);
        $macchiato->tags()->attach(Tag::where('key', 'vegan')->firstOrFail()->id, ['tenant_id' => $macchiato->tenant_id]);
        $buna = Item::create(['category_id' => $category->id, 'name' => ['en' => 'Buna'], 'price_minor' => null, 'sort' => 2]);
        ItemVariant::create(['item_id' => $buna->id, 'name' => ['en' => 'Cup'], 'price_minor' => 6000, 'sort' => 0]);
        ItemVariant::create(['item_id' => $buna->id, 'name' => ['en' => 'Pot for 4'], 'price_minor' => 20000, 'sort' => 1]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Gone cake'], 'price_minor' => 5000, 'is_sold_out' => true, 'sort' => 3]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Secret dish'], 'price_minor' => 5000, 'is_visible' => false, 'sort' => 4]);
    });

    $text = pdfText($tenant);

    expect($text)->toStartWith('%PDF')
        ->toContain('(Buna & Bread)')->toContain('(Coffee)')
        ->toContain('(Macchiato)')->toContain('(Br 120)')
        ->toContain('(Espresso with a spoon of foam)')
        ->toContain('Cup Br 60')->toContain('Pot for 4 Br 200')
        ->toContain('(Vegan)')
        ->not->toContain('Gone cake')->not->toContain('Secret dish');
});

it('lays out one or two columns, and uses the brand colour', function () {
    $tenant = Tenant::factory()->create();
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        foreach (range(1, 60) as $n) {
            Item::create(['category_id' => $category->id, 'name' => ['en' => 'Dish '.$n], 'summary' => ['en' => 'A short description of dish '.$n], 'price_minor' => 1000 * $n, 'sort' => $n]);
        }
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'primary_color' => '#0d7a5f']);
    });
    $pages = fn (string $pdf) => preg_match_all('#/Type /Page\b#', $pdf);

    $one = pdfText($tenant, ['columns' => 1]);
    $two = pdfText($tenant, ['columns' => 2]);

    expect($pages($two))->toBeLessThan($pages($one))
        // 13 122 95 = #0d7a5f in PDF's 0–1 colour numbers.
        ->and($one)->toContain('0.051 0.478 0.373 rg');
});

it('lets owners and managers download it with the chosen options', function () {
    [$owner, $tenant] = member(Role::Owner);
    [$menu] = sampleMenu($tenant);

    $response = $this->actingAs($owner)->get(t($tenant, "menu-pdf?menu_id={$menu->id}&photos=1&columns=2"))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment; filename="')->toEndWith('-menu.pdf"')
        ->and((string) $response->getContent())->toStartWith('%PDF');

    $this->actingAs($owner)->getJson(t($tenant, 'menu-pdf?columns=3'))->assertInvalid('columns');
    [$staff] = member(Role::Staff, $tenant);
    $this->actingAs($staff)->getJson(t($tenant, 'menu-pdf'))->assertForbidden();
});

it('respects the plan feature', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    DB::table('tenant_feature_overrides')->insert(['tenant_id' => $tenant->id, 'feature_key' => 'pdf_menu', 'enabled' => false]);
    Features::forget();

    $this->actingAs($owner)->getJson(t($tenant, 'menu-pdf'))->assertStatus(402);
});

it('offers guests a cached PDF only when the restaurant switches the link on, rebuilt after changes', function () {
    [$owner, $tenant] = member(Role::Owner);
    [, , $item] = sampleMenu($tenant);
    File::deleteDirectory(public_path('menu-cache'));

    $this->get('/'.$tenant->slug)->assertOk()->assertDontSee('menu.pdf');
    $this->get('/'.$tenant->slug.'/menu.pdf')->assertNotFound();

    asTenant($tenant, fn () => TenantAppearance::current()->fill(['show_pdf_link' => true])->save());
    $tenant->bumpCacheVersion();

    $this->get('/'.$tenant->slug)->assertOk()->assertSee('href="/'.$tenant->slug.'/menu.pdf">Download menu (PDF)</a>', false);
    $this->get('/'.$tenant->slug.'/menu.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $file = MenuPageCache::dir($tenant->slug).'/menu.pdf';
    expect($file)->toBeFile();

    // Any menu change removes it; the next request builds it again.
    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['price_minor' => 15000], idem())->assertOk();
    expect($file)->not->toBeFile();
    $this->get('/'.$tenant->slug.'/menu.pdf')->assertOk();
    expect($file)->toBeFile();

    File::deleteDirectory(public_path('menu-cache'));
});
