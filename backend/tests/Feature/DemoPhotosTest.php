<?php

use App\Console\Commands\DownloadDemoPhotos;
use App\Models\Tenant;
use App\Support\ImageProcessor;
use Database\Seeders\Demo\Photos;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function jpegBytes(int $w, int $h): string
{
    $img = imagecreatetruecolor($w, $h);
    for ($i = 0; $i < 400; $i++) { // some detail so the file is not tiny
        imagefilledellipse($img, random_int(0, $w), random_int(0, $h), 40, 40, (int) imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
    }
    ob_start();
    imagejpeg($img, null, 90);

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/demo-photos-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    config(['menu.demo_photos_path' => $this->dir]);
    File::put($this->dir.'/photos.json', json_encode(['photos' => [
        ['key' => 'banner-a', 'source' => 'unsplash', 'id' => 'AbC123', 'page' => 'https://unsplash.com/photos/x-AbC123', 'photographer' => 'Jane Doe', 'profile' => 'https://unsplash.com/@jane', 'licence' => 'Unsplash License', 'banner' => true],
        ['key' => 'dish-b', 'source' => 'pexels', 'id' => '4567', 'page' => 'https://www.pexels.com/photo/dish-4567/', 'photographer' => 'cook-1', 'profile' => 'https://www.pexels.com/@cook-1', 'licence' => 'Pexels License', 'banner' => false],
    ]]));
    File::deleteDirectory(storage_path('app/demo-photo-cache'));
});

afterEach(function () {
    File::deleteDirectory($this->dir);
    File::deleteDirectory(storage_path('app/demo-photo-cache'));
});

it('downloads the listed photos as WebP within the size limits and writes the credits', function () {
    Http::fake([
        'unsplash.com/photos/AbC123/download*' => Http::response('', 302, ['Location' => 'https://images.unsplash.com/photo-1-abc?ixid=x&fm=jpg&q=85']),
        'images.unsplash.com/*' => Http::response(jpegBytes(2400, 1600), 200, ['Content-Type' => 'image/jpeg']),
        'images.pexels.com/photos/4567/pexels-photo-4567.jpeg*' => Http::response('', 404),
        'images.pexels.com/photos/4567/pexels-cook-1-4567.jpg*' => Http::response(jpegBytes(1200, 1800), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->artisan('demo:photos')->assertSuccessful();

    expect(array_slice(getimagesize($this->dir.'/banner-a.webp'), 0, 2))->toBe([DownloadDemoPhotos::BANNER_SIDE, 853]);
    expect(getimagesize($this->dir.'/dish-b.webp')[1])->toBe(DownloadDemoPhotos::PHOTO_SIDE) // portrait: the long side
        ->and(getimagesize($this->dir.'/dish-b.webp')['mime'])->toBe('image/webp');
    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://images.unsplash.com/photo-1-abc?w=1600'));
    expect(File::get($this->dir.'/CREDITS.md'))->toContain('| `banner-a.webp` | [unsplash AbC123](https://unsplash.com/photos/x-AbC123) | [Jane Doe](https://unsplash.com/@jane) | Unsplash License |')
        ->toContain('Pexels License');

    // Already there: nothing is downloaded again.
    Http::fake(fn () => throw new RuntimeException('no request expected'));
    $this->artisan('demo:photos')->assertSuccessful();
});

it('lowers the quality to stay within the budget and reports photos it could not get', function () {
    Http::fake([
        'unsplash.com/*' => Http::response('', 500),
        'images.pexels.com/*' => Http::response(jpegBytes(1600, 1200), 200, ['Content-Type' => 'image/jpeg']),
    ]);
    $this->artisan('demo:photos', ['--budget' => 0.005])
        ->expectsOutputToContain('Over the budget')
        ->expectsOutputToContain('Not downloaded: banner-a')
        ->assertFailed();

    expect(is_file($this->dir.'/dish-b.webp'))->toBeTrue()->and(is_file($this->dir.'/banner-a.webp'))->toBeFalse();
});

it('uses the downloaded photo in the seeders, or a generated picture when it is missing', function () {
    Storage::fake('public');
    $photo = imagecreatetruecolor(900, 600);
    imagewebp($photo, $this->dir.'/dish-b.webp', 75);
    $photos = new Photos(app(ImageProcessor::class));
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    [$real, $generated] = asTenant($a, fn () => [$photos->media($a, 'dish-b'), $photos->media($a, 'banner-a', true)]);
    $copy = asTenant($b, fn () => $photos->media($b, 'dish-b'));

    expect($real->width)->toBe(900)->and($generated->width)->toBe(800)
        ->and($copy->variants)->toBe($real->variants)->and($copy->base_path)->toBe($b->id.'/demo-dish-b');
    Storage::disk('public')->assertExists($b->id.'/demo-dish-b/640.webp');
});

it('keeps the committed credits in line with the photo list, and only free licences', function () {
    config(['menu.demo_photos_path' => null]);
    $photos = DownloadDemoPhotos::manifest();

    expect(File::get(Photos::directory().'/CREDITS.md'))->toBe(DownloadDemoPhotos::credits($photos));
    foreach ($photos as $p) {
        expect($p['licence'])->toBeIn(['Unsplash License', 'Pexels License'])
            ->and($p['page'])->toStartWith($p['source'] === 'unsplash' ? 'https://unsplash.com/photos/' : 'https://www.pexels.com/photo/')
            ->and($p['page'])->toContain($p['id']);
    }
    expect(collect($photos)->pluck('key')->duplicates())->toBeEmpty();
});

it('follows relative and repeated Unsplash redirects to the image', function () {
    File::put($this->dir.'/photos.json', json_encode(['photos' => [
        ['key' => 'rel', 'source' => 'unsplash', 'id' => 'Rel1', 'page' => 'https://unsplash.com/photos/x-Rel1', 'photographer' => 'A', 'profile' => null, 'licence' => 'Unsplash License', 'banner' => false],
    ]]));
    Http::fake([
        'unsplash.com/photos/Rel1/download?ixid=abc' => Http::response('', 302, ['Location' => '//images.unsplash.com/photo-9-xyz?ixid=abc&fm=jpg']),
        'unsplash.com/photos/Rel1/download?force=true' => Http::response('', 302, ['Location' => '/photos/Rel1/download?ixid=abc']),
        'images.unsplash.com/*' => Http::response(jpegBytes(1600, 1000), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->artisan('demo:photos')->assertSuccessful();

    expect(is_file($this->dir.'/rel.webp'))->toBeTrue();
    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://images.unsplash.com/photo-9-xyz?w=1600'));
    expect(DownloadDemoPhotos::absolute('/a/b', 'https://unsplash.com/x'))->toBe('https://unsplash.com/a/b');
});

it('follows 301, 302, 307 and 308 redirects to the image', function (int $status) {
    File::put($this->dir.'/photos.json', json_encode(['photos' => [
        ['key' => 'moved', 'source' => 'pexels', 'id' => '777', 'page' => 'https://www.pexels.com/photo/x-777/', 'photographer' => 'cook-1', 'profile' => null, 'licence' => 'Pexels License', 'banner' => false],
    ]]));
    Http::fake([
        'images.pexels.com/photos/777/*' => Http::response('', $status, ['Location' => 'https://cdn.example.test/777.jpg']),
        'cdn.example.test/*' => Http::response(jpegBytes(1200, 800), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->artisan('demo:photos')->assertSuccessful();

    expect(is_file($this->dir.'/moved.webp'))->toBeTrue();
    Http::assertSent(fn (Request $r) => $r->url() === 'https://cdn.example.test/777.jpg');
})->with([301, 302, 307, 308]);

it('keeps following redirects after a hop where they were turned off', function () {
    expect(DownloadDemoPhotos::client(follow: false)->getOptions()['allow_redirects'])->toBeFalse()
        ->and(DownloadDemoPhotos::client()->getOptions()['allow_redirects'])->toBeArray();
});
