<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\ImageProcessor;
use App\Support\PlatformSettings;
use App\Support\PublicMenu\ShareImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Link previews when the menu is shared (owner, 2026-11-12). Fetches the page
 * and reads its <head> like a chat app does.
 *
 * @return array<string, string> property/name => content
 */
function previewTags(string $html): array
{
    $doc = new DOMDocument;
    @$doc->loadHTML($html);
    $tags = [];
    foreach ($doc->getElementsByTagName('meta') as $m) {
        $key = $m->getAttribute('property') ?: $m->getAttribute('name');
        if ($key !== '') {
            $tags[$key] = $m->getAttribute('content');
        }
    }
    foreach ($doc->getElementsByTagName('link') as $l) {
        if ($l->getAttribute('rel') === 'canonical') {
            $tags['canonical'] = $l->getAttribute('href');
        }
    }

    return $tags;
}

beforeEach(function () {
    config(['app.url' => 'https://menu.example.com']);
    Storage::fake('public', ['url' => 'https://menu.example.com/media']);
});

function bannerFor(Tenant $tenant): Media
{
    return asTenant($tenant, fn () => Media::create(['base_path' => 'b/'.uniqid(), 'mime' => 'image/jpeg', 'width' => 1600, 'height' => 800, 'bytes' => 1,
        'variants' => ['320' => '320.webp', '640' => '640.webp', '800' => '800.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg']]));
}

it('gives every branch URL a complete large-image preview with absolute https addresses', function () {
    [$owner, $tenant] = member(Role::Owner);
    $tenant->update(['name' => 'Buna & Bread']);
    sampleMenu($tenant);
    $this->actingAs($owner)->postJson(t($tenant, 'branches'), ['name' => ['en' => 'Piassa'], 'slug' => 'piassa'], idem())->assertCreated();
    $banner = bannerFor($tenant);
    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, 'cover_media_id' => $banner->id]));
    $main = asTenant($tenant, fn () => Branch::where('is_default', true)->sole()->name['en']);

    $tags = previewTags((string) $this->get('/'.$tenant->slug.'/piassa')->assertOk()->getContent());

    expect($tags)->toMatchArray([
        'og:type' => 'website',
        'og:url' => 'https://menu.example.com/'.$tenant->slug.'/piassa',
        'canonical' => 'https://menu.example.com/'.$tenant->slug.'/piassa',
        'og:title' => 'Buna & Bread · Piassa',
        'og:image:type' => 'image/jpeg',
        'og:image:width' => '1200',
        'og:image:height' => '630',
        'og:image:alt' => 'Buna & Bread · Piassa — menu',
        'twitter:card' => 'summary_large_image',
        'twitter:title' => 'Buna & Bread · Piassa',
    ])->and($tags['og:description'])->toBe('The menu of Buna & Bread: dishes, prices and opening hours.')
        ->and($tags['og:image'])->toStartWith('https://menu.example.com/media/'.$banner->base_path.'/og.jpg?v=')
        ->and($tags['og:image:secure_url'])->toBe($tags['og:image'])
        ->and($tags['twitter:image'])->toBe($tags['og:image']);
    expect(previewTags((string) $this->get('/'.$tenant->slug)->getContent()))
        ->toMatchArray(['og:url' => 'https://menu.example.com/'.$tenant->slug, 'og:title' => 'Buna & Bread · '.$main]);
});

it('never loads the preview picture on the menu page itself', function () {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    $banner = bannerFor($tenant);
    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, 'cover_media_id' => $banner->id]));
    $html = (string) $this->get('/'.$tenant->slug)->getContent();

    $body = substr($html, strpos($html, '</head>'));
    expect($body)->not->toContain('og.jpg')->not->toContain('share-default')
        ->and($html)->not->toMatch('#<link rel="preload"[^>]*og\.jpg#');
});

it('uses the platform default without a banner, and Super Admin can replace and reset it', function () {
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    expect(previewTags((string) $this->get('/'.$tenant->slug)->getContent())['og:image'])
        ->toStartWith('https://menu.example.com/images/share-default.jpg?v=');
    expect(array_slice((array) getimagesize(public_path(ShareImage::BUILT_IN)), 0, 2))->toBe([1200, 630]);

    $admin = superAdmin();
    $upload = UploadedFile::fake()->image('cover.png', 2400, 1600);
    $this->actingAs($admin)->post(p('settings/share-image'), ['image' => $upload], [...idem(), 'Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('data.share_image_url', fn ($u) => str_starts_with($u, 'https://menu.example.com/media/platform/share-'));
    $path = PlatformSettings::get('share_image');
    Storage::disk('public')->assertExists($path);
    [$w, $h, $type] = getimagesizefromstring((string) Storage::disk('public')->get($path));
    expect([$w, $h, $type])->toBe([1200, 630, IMAGETYPE_JPEG]);
    // Static pages were cleared, so the menu shows the new picture straight away.
    expect(previewTags((string) $this->get('/'.$tenant->slug)->getContent())['og:image'])->toBe('https://menu.example.com/media/'.$path);

    $this->actingAs($admin)->post(p('settings/share-image'), ['image' => UploadedFile::fake()->image('tiny.png', 200, 100)], [...idem(), 'Accept' => 'application/json'])->assertInvalid('image');
    $this->actingAs($admin)->deleteJson(p('settings/share-image'), [], idem())->assertOk();
    Storage::disk('public')->assertMissing($path);
    expect(previewTags((string) $this->get('/'.$tenant->slug)->getContent())['og:image'])->toContain('/images/share-default.jpg');

    [$owner] = member(Role::Owner);
    $this->actingAs($owner)->deleteJson(p('settings/share-image'), [], idem())->assertNotFound(); // the platform area is invisible to restaurants
});

it('points at the new picture when the banner changes', function () {
    [$owner, $tenant] = member(Role::Owner);
    sampleMenu($tenant);
    $first = bannerFor($tenant);
    asTenant($tenant, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, 'cover_media_id' => $first->id]));
    $before = previewTags((string) $this->get('/'.$tenant->slug)->getContent())['og:image'];

    $second = bannerFor($tenant);
    $this->actingAs($owner)->putJson(t($tenant, 'appearance'), [...TenantAppearance::DEFAULTS, 'cover_media_id' => $second->id], idem())->assertOk();
    $after = previewTags((string) $this->get('/'.$tenant->slug)->getContent())['og:image'];

    expect($after)->not->toBe($before)->toContain($second->base_path.'/og.jpg?v=');
});

it('makes a 1200 × 630 JPEG under 300 KB, even from a busy photo', function () {
    $img = imagecreatetruecolor(2400, 1800);
    for ($i = 0; $i < 60000; $i++) {
        imagefilledrectangle($img, $x = random_int(0, 2399), $y = random_int(0, 1799), $x + random_int(1, 6), $y + random_int(1, 6), random_int(0, 0xFFFFFF));
    }
    app(ImageProcessor::class)->shareImage($img, 'public', 'x/og.jpg');

    $bytes = (string) Storage::disk('public')->get('x/og.jpg');
    expect(strlen($bytes))->toBeLessThanOrEqual(ImageProcessor::SHARE_MAX_BYTES)
        ->and(array_slice(getimagesizefromstring($bytes), 0, 3))->toBe([1200, 630, IMAGETYPE_JPEG]);
});

it('adds the preview to banners uploaded before it existed (media:regenerate)', function () {
    $tenant = Tenant::factory()->create();
    $media = asTenant($tenant, function () {
        $img = imagecreatetruecolor(1280, 640);
        ob_start();
        imagewebp($img);
        Storage::disk('public')->put('old/1280.webp', (string) ob_get_clean());

        return Media::create(['base_path' => 'old', 'mime' => 'image/jpeg', 'width' => 1280, 'height' => 640, 'bytes' => 1,
            'variants' => ['320' => '1280.webp', '640' => '1280.webp', '800' => '1280.webp', '1280' => '1280.webp', 'jpg' => '1280.webp']]);
    });

    $this->artisan('media:regenerate')->assertSuccessful();

    expect($media->fresh()->variants)->toHaveKey('og', 'og.jpg');
    Storage::disk('public')->assertExists('old/og.jpg');
});
