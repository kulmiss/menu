<?php

use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\ImageProcessor;
use Illuminate\Support\Facades\Storage;

/** A photo stored the old way: 320/640/1280 only. */
function oldPhoto(Tenant $tenant, string $folder): Media
{
    $img = imagecreatetruecolor(1600, 1000);
    imagefill($img, 0, 0, (int) imagecolorallocate($img, 180, 90, 40));
    foreach ([320, 640, 1280] as $w) {
        ob_start();
        imagewebp(imagescale($img, $w), null, 72);
        Storage::disk('public')->put("{$folder}/{$w}.webp", (string) ob_get_clean());
    }

    return asTenant($tenant, fn () => Media::create([
        'disk' => 'public', 'base_path' => $folder, 'mime' => 'image/jpeg', 'width' => 1600, 'height' => 1000, 'bytes' => 1,
        'variants' => ['320' => '320.webp', '640' => '640.webp', '1280' => '1280.webp', 'jpg' => '640.jpg', 'og' => 'og.jpg'],
    ]));
}

it('makes the missing 800 px size for older photos and rebuilds their menus', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    sampleMenu($tenant);
    $photo = oldPhoto($tenant, $tenant->id.'/old');
    $version = $tenant->fresh()->cache_version;

    $this->artisan('media:regenerate --dry-run')->expectsOutput('1 photo(s) are missing sizes.')->assertSuccessful();
    Storage::disk('public')->assertMissing($tenant->id.'/old/800.webp');

    $this->artisan('media:regenerate')->expectsOutput('1 photo(s) updated, 0 failed, 1 menu(s) rebuilt.')->assertSuccessful();

    Storage::disk('public')->assertExists($tenant->id.'/old/800.webp');
    expect(getimagesizefromstring((string) Storage::disk('public')->get($tenant->id.'/old/800.webp'))[0])->toBe(800);
    $variants = asTenant($tenant, fn () => Media::find($photo->id))->variants;
    expect($variants)->toHaveKeys(['320', '640', '800', '1280', 'jpg', 'og'])
        ->and($variants['1280'])->toBe('1280.webp');
    expect($tenant->fresh()->cache_version)->not->toBe($version);

    // Running again changes nothing.
    $this->artisan('media:regenerate')->expectsOutput('0 photo(s) updated, 0 failed, 0 menu(s) rebuilt.')->assertSuccessful();
});

it('saves a banner at the banner quality and can be limited to one restaurant', function () {
    Storage::fake('public');
    [$a, $b] = [Tenant::factory()->create(), Tenant::factory()->create()];
    $banner = oldPhoto($a, $a->id.'/banner');
    $dish = oldPhoto($a, $a->id.'/dish');
    oldPhoto($b, $b->id.'/dish');
    asTenant($a, fn () => TenantAppearance::create([...TenantAppearance::DEFAULTS, 'cover_media_id' => $banner->id]));

    $this->artisan('media:regenerate --tenant='.$a->slug)->assertSuccessful();

    $size = fn (string $path) => strlen((string) Storage::disk('public')->get($path));
    expect($size($a->id.'/banner/800.webp'))->toBeLessThanOrEqual($size($a->id.'/dish/800.webp'));
    Storage::disk('public')->assertMissing($b->id.'/dish/800.webp');
    $this->artisan('media:regenerate --tenant=nope')->assertFailed();
});

it('reports photos whose files are gone without stopping', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create();
    asTenant($tenant, fn () => Media::create([
        'disk' => 'public', 'base_path' => 'gone', 'mime' => 'image/jpeg', 'width' => 1, 'height' => 1, 'bytes' => 1,
        'variants' => ['320' => '320.webp', '1280' => '1280.webp'],
    ]));
    oldPhoto($tenant, 'ok');

    $this->artisan('media:regenerate')->expectsOutputToContain('1 photo(s) updated, 1 failed')->assertFailed();
    expect(ImageProcessor::WIDTHS)->toContain(800);
});
