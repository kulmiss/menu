<?php

use App\Enums\Role;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Media;
use App\Models\Promotion;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\Features;

/** Style D · Minimal (owner, 2026-11-12): text only, banner and logo optional. */
function minimalPage(array $appearance = [], ?callable $extra = null): string
{
    $tenant = Tenant::factory()->create();
    [, $category, $item] = sampleMenu($tenant);
    asTenant($tenant, function () use ($category, $item, $appearance, $extra) {
        $photo = fn (string $p) => Media::create([
            'base_path' => $p, 'mime' => 'image/jpeg', 'width' => 1600, 'height' => 800, 'bytes' => 1,
            'variants' => ['320' => '320.webp', '640' => '640.webp', '800' => '800.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
        ]);
        $item->update(['media_id' => $photo('dish/1')->id]);
        $tea = Item::create(['category_id' => $category->id, 'name' => ['en' => 'Tea'], 'media_id' => $photo('dish/2')->id, 'sort' => 5]);
        ItemVariant::create(['item_id' => $tea->id, 'name' => ['en' => 'Small'], 'price_minor' => 6000, 'sort' => 0]);
        ItemVariant::create(['item_id' => $tea->id, 'name' => ['en' => 'Large'], 'price_minor' => 8050, 'sort' => 1]);
        Item::create(['category_id' => $category->id, 'name' => ['en' => 'Shiro'], 'price_minor' => 22000, 'is_sold_out' => true, 'sort' => 6])
            ->tags()->attach(Tag::where('key', 'vegan')->firstOrFail()->id, ['tenant_id' => $category->tenant_id]);
        Promotion::create(['type' => 'special', 'title' => ['en' => 'Sunday ceremony'], 'media_id' => $photo('promo/1')->id]);
        TenantAppearance::create([...TenantAppearance::DEFAULTS, 'style' => 'minimal', 'show_photos' => true,
            'logo_media_id' => $photo('logo/1')->id, 'cover_media_id' => $photo('banner/1')->id, ...$appearance]);
        if ($extra) {
            $extra();
        }
    });

    return (string) test()->get('/'.$tenant->slug)->assertOk()->getContent();
}

it('shows no dish or special photos, even with "Show photos" on', function () {
    $html = minimalPage();

    expect($html)->toContain('class="S-minimal')
        ->not->toContain('dish/1')->not->toContain('dish/2')->not->toContain('promo/1')
        // The banner still loads first, like in the other styles.
        ->toMatch('#<link rel="preload" as="image" fetchpriority="high"[^>]*banner/1/800.webp#')
        ->toContain('logo/1/');
    // The dish sheet gets no photo gallery either.
    preg_match('#<script type="application/json" id="d">(.*?)</script>#s', $html, $m);
    expect(json_decode($m[1], true)['c'])->each(fn ($c) => $c->not->toHaveKeys(['i', 'g']));
});

it('lists sizes on the menu, tags as text and sold-out dishes greyed with "Sold out"', function () {
    $html = minimalPage();

    expect($html)->toContain('<span class="pr sz">Small 60 · Large 80.50</span>')
        ->toContain('<li data-i="')->toMatch('#class="so">\s*<button[^>]*><span class="tx"><span class="nm">Shiro</span>#')
        ->toContain('<span class="bd x0">Sold out</span>')->toContain('<span class="bd">Vegan</span>');
    // Labels are plain text (no pills), and the brand colour only marks small accents.
    expect($html)->toContain('.bd,.bd.hl,.so .bd.x0{background:none;border:0;padding:0');
});

it('loads no image at all with the banner and logo switched off', function () {
    $html = minimalPage(['show_banner' => false, 'show_logo' => false]);

    expect($html)->not->toContain('<img')->not->toContain('<picture')->not->toContain('rel="preload" as="image"')
        ->not->toContain('banner/1')->not->toContain('logo/1');
});

it('saves style D with the logo and banner switched off', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...TenantAppearance::DEFAULTS, 'style' => 'minimal', 'show_banner' => false, 'show_logo' => false], idem())
        ->assertOk()->assertJsonPath('data.style', 'minimal')->assertJsonPath('data.show_banner', false)->assertJsonPath('data.show_logo', false);
});

it('is on every plan by default and can be switched off per plan', function () {
    $tenant = Tenant::factory()->create();
    expect(Features::styles($tenant))->toContain('minimal');

    $this->actingAs(superAdmin())->putJson(p("restaurants/{$tenant->id}/features"), ['features' => ['style_minimal' => false]], idem())->assertOk();
    Features::forget();
    expect(Features::styles($tenant->fresh()))->not->toContain('minimal');
});
