<?php

use App\Enums\Role;
use App\Models\Media;
use App\Support\ImageProcessor;
use Illuminate\Http\UploadedFile;

/** SECURITY_REPORT.md M6: decompression bombs are refused from the header, before decoding. */
function bombPng(int $w, int $h): string
{
    // A valid PNG header claiming $w × $h, with one tiny zlib stream: a few hundred bytes on disk.
    $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)).$chunk('IDAT', (string) gzcompress(str_repeat("\0", 1024))).$chunk('IEND', '');
    $path = tempnam(sys_get_temp_dir(), 'bomb').'.png';
    file_put_contents($path, $png);

    return $path;
}

it('refuses a 30 000 × 30 000 px photo without decoding it, and accepts a normal one', function () {
    [$owner, $tenant] = member(Role::Owner);
    $path = bombPng(30000, 30000);
    expect(filesize($path))->toBeLessThan(2000);

    $before = memory_get_peak_usage(true);
    $this->actingAs($owner)->post(t($tenant, 'media'), ['file' => new UploadedFile($path, 'menu.png', 'image/png', null, true)], ['Accept' => 'application/json'] + idem())
        ->assertUnprocessable()->assertJsonPath('errors.file.0', __('menu.media.too_many_pixels'));
    expect(memory_get_peak_usage(true) - $before)->toBeLessThan(64 * 1024 * 1024)
        ->and(asTenant($tenant, fn () => Media::count()))->toBe(0);

    $this->post(t($tenant, 'media'), ['file' => UploadedFile::fake()->image('dish.jpg', 1600, 1200)], ['Accept' => 'application/json'] + idem())->assertCreated();
});

it('checks pixels, the longest side and the server memory limit', function () {
    expect(ImageProcessor::safeSize(bombPng(8000, 6000)))->toBe([8000, 6000])          // 48 MP phone photo
        ->and(ImageProcessor::safeSize(bombPng(9000, 6000)))->toBeNull()                // 54 MP
        ->and(ImageProcessor::safeSize(bombPng(20000, 100)))->toBeNull()                // side too long
        ->and(ImageProcessor::safeSize(tempnam(sys_get_temp_dir(), 'x')))->toBeNull(); // not an image

    $old = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage(true) + 64 * 1024 * 1024));
    try {
        expect(ImageProcessor::safeSize(bombPng(4000, 3000)))->toBeNull()  // ~108 MB needed
            ->and(ImageProcessor::safeSize(bombPng(1600, 1200)))->toBe([1600, 1200]);
    } finally {
        ini_set('memory_limit', (string) $old);
    }
});

it('refuses a bomb as the e-mail logo too', function () {
    $this->actingAs(superAdmin())->post(p('settings/logo'), ['image' => new UploadedFile(bombPng(30000, 30000), 'logo.png', 'image/png', null, true)], ['Accept' => 'application/json'] + idem())
        ->assertStatus(422);
});
