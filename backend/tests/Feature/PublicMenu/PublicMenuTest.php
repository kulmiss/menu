<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Menu;
use App\Models\MenuSchedule;
use App\Models\Tenant;
use App\Models\TenantSlugHistory;
use App\Support\PublicMenu\MenuPageCache;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/** Lets a test write static pages into a throw-away public folder. */
function withStaticPages(): string
{
    $dir = sys_get_temp_dir().'/menu-public-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($dir);
    app()->usePublicPath($dir);
    config(['menu.static_pages' => true]);

    return $dir;
}

it('shows the visible menu with prices, labels and the item details data', function () {
    $tenant = Tenant::factory()->create(['name' => 'Cafe Uno']);
    [$menu, $category, $item] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category) {
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Secret'], 'price_minor' => 100, 'is_visible' => false]);
        $sized = Item::create(['category_id' => $category->id, 'name' => ['en' => 'Juice'], 'is_sold_out' => true, 'description' => ['en' => 'Fresh mango']]);
        $sized->variants()->create(['name' => ['en' => 'Large'], 'price_minor' => 9050]);
        $sized->variants()->create(['name' => ['en' => 'Small'], 'price_minor' => 6000]);
    });

    $html = $this->get('/'.$tenant->slug)->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('Cafe Uno')->assertSee('Macchiato')->assertSee('Br 120')
        ->assertSee('from Br 60')->assertSee('Sold out')->assertSee('Fresh mango')->assertSee('Br 90.50')
        ->assertDontSee('Secret')
        ->getContent();

    expect($html)->toContain('<html lang="en">')
        ->not->toContain('<script src')       // nothing to download: CSS and JS are inline
        ->not->toContain('<link rel="stylesheet"');
});

it('escapes restaurant text so it cannot inject markup or script', function () {
    $tenant = Tenant::factory()->create(['name' => 'Bad <b>bold</b>']);
    [, $category] = sampleMenu($tenant);
    asTenant($tenant, fn () => Item::create([
        'category_id' => $category->id,
        'name' => ['en' => '<img src=x onerror=alert(1)>'],
        'price_minor' => 100,
        'description' => ['en' => '</script><script>alert(2)</script>'],
    ]));

    $html = $this->get('/'.$tenant->slug)->assertOk()->getContent();

    expect($html)->not->toContain('<img src=x')
        ->not->toContain('<b>bold</b>')
        ->not->toContain('</script><script>alert(2)');
});

it('marks menus served at set times so the page can label them', function () {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    asTenant($tenant, function () {
        $breakfast = Menu::create(['name' => ['en' => 'Breakfast'], 'slug' => 'breakfast', 'schedule_mode' => Menu::SCHEDULED]);
        MenuSchedule::create(['menu_id' => $breakfast->id, 'weekday' => 1, 'starts_at' => '07:00', 'ends_at' => '11:00']);
        $c = Category::create(['menu_id' => $breakfast->id, 'name' => ['en' => 'Eggs']]);
        Item::create(['category_id' => $c->id, 'name' => ['en' => 'Omelette'], 'price_minor' => 100]);
    });

    $this->get('/'.$tenant->slug)->assertOk()
        ->assertSee('data-s="[[1,&quot;07:00&quot;,&quot;11:00&quot;]]"', false)
        ->assertSee('Not served right now');
});

it('sends old addresses to the new one and unknown ones to 404', function () {
    $tenant = Tenant::factory()->create(['slug' => 'new-name']);
    TenantSlugHistory::create(['tenant_id' => $tenant->id, 'old_slug' => 'old-name']);

    $this->get('/old-name?table=4')->assertStatus(301)->assertRedirect('/new-name?table=4');
    $this->get('/nobody-here')->assertNotFound();
});

it('shows a friendly page instead of the menu when the restaurant is unavailable', function (string $state) {
    $tenant = $state === 'suspended'
        ? Tenant::factory()->create(['status' => Tenant::STATUS_SUSPENDED])
        : Tenant::factory()->expired(60)->create();
    sampleMenu($tenant);

    $this->get('/'.$tenant->slug)->assertStatus(503)
        ->assertSee('Menu unavailable')->assertDontSee('Macchiato')
        ->assertHeader('X-Robots-Tag', 'noindex');
})->with(['suspended', 'unavailable']);

it('answers a repeat visit with 304 when the page has not changed', function () {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);

    $etag = $this->get('/'.$tenant->slug)->assertOk()->headers->get('ETag');

    $this->get('/'.$tenant->slug, ['If-None-Match' => $etag])->assertStatus(304);
});

it('loads the page with a fixed number of queries, however big the menu (no N+1)', function () {
    $tenant = Tenant::factory()->create();
    [$menu] = sampleMenu($tenant);
    $count = function () use ($tenant) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/'.$tenant->slug)->assertOk();

        return count(DB::getQueryLog());
    };

    $count(); // warm-up: platform settings and features are cached after the first build
    $small = $count();
    asTenant($tenant, function () use ($menu) {
        for ($c = 0; $c < 4; $c++) {
            $category = Category::create(['menu_id' => $menu->id, 'name' => ['en' => "C{$c}"]]);
            for ($i = 0; $i < 6; $i++) {
                $item = Item::create(['category_id' => $category->id, 'name' => ['en' => "I{$c}-{$i}"], 'price_minor' => 100]);
                $item->variants()->create(['name' => ['en' => 'L'], 'price_minor' => 200]);
            }
        }
    });
    $big = $count();

    // Constant, not per item. The page is built only when its static copy is
    // missing (after a change), so a few more queries cost nothing at visit time.
    expect($big)->toBe($small)->toBeLessThanOrEqual(20);
});

it('stores a static copy, serves it, and removes it when the menu changes', function () {
    $public = withStaticPages();
    $tenant = Tenant::factory()->create();
    [, , $item] = sampleMenu($tenant);

    $this->get('/'.$tenant->slug)->assertOk()->assertSee('Macchiato');
    $file = $public.'/menu-cache/'.$tenant->slug.'/index.html';
    expect($file)->toBeFile()
        ->and(gzdecode((string) file_get_contents($file.'.gz')))->toBe(file_get_contents($file));

    // The second visit is answered from the copy, without loading the menu.
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get('/'.$tenant->slug)->assertOk()->assertSee('Macchiato');
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(2);

    // A change made through the API removes the copy once it is saved.
    [$owner] = member(tenant: $tenant);
    $this->actingAs($owner)->patchJson(t($tenant, "items/{$item->id}"), ['name' => ['en' => 'Cortado']], idem())->assertOk();
    expect($file)->not->toBeFile();
    $this->get('/'.$tenant->slug)->assertSee('Cortado')->assertDontSee('Macchiato');

    File::deleteDirectory($public);
});

it('removes static pages of restaurants whose subscription ran out', function () {
    $public = withStaticPages();
    $active = Tenant::factory()->create();
    $expired = Tenant::factory()->create();
    sampleMenu($active);
    sampleMenu($expired);
    $this->get('/'.$active->slug)->assertOk();
    $this->get('/'.$expired->slug)->assertOk();
    MenuPageCache::write('deleted-restaurant', '<p>old</p>');

    $expired->forceFill(['trial_ends_at' => now()->subDays(60)])->save();
    $this->artisan('menu:refresh-pages')->assertSuccessful();

    expect(MenuPageCache::path($active->slug))->toBeFile()
        ->and(MenuPageCache::path($expired->slug))->not->toBeFile()
        ->and(MenuPageCache::path('deleted-restaurant'))->not->toBeFile();

    $this->artisan('menu:refresh-pages --all')->assertSuccessful();
    expect(MenuPageCache::path($active->slug))->not->toBeFile();

    File::deleteDirectory($public);
});

it('keeps the demo menu page within the 30 KB budget (PLAN.md §15.1)', function () {
    Storage::fake('public');
    $this->seed(DemoSeeder::class);

    $html = $this->get('/'.DemoSeeder::SLUG)->assertOk()->getContent();
    $gzip = strlen((string) gzencode((string) $html, 9));

    expect($gzip)->toBeLessThanOrEqual(30 * 1024);
    expect(preg_match('#<script>(.*?)</script>#s', (string) $html, $js))->toBe(1);
    expect(strlen($js[1]))->toBeLessThan(5 * 1024);  // inline JS under 5 KB (§15.2.2)
});

it('accepts the visit beacon without an idempotency key', function () {
    $this->postJson('/api/v1/beacon', ['t' => 1])->assertNoContent();
});
