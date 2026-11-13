<?php

namespace Database\Seeders\Demo;

use App\Models\Media;
use App\Models\Tenant;
use App\Support\ImageProcessor;
use Illuminate\Support\Facades\Storage;

/**
 * Demo photos for the seeders. Real photos (Unsplash / Pexels licence, see
 * demo-photos/CREDITS.md) are downloaded once with `php artisan demo:photos`
 * into database/seeders/demo-photos/{key}.webp. When a file is missing
 * (CI, a fresh clone), a small generated picture is used instead, so the
 * demo always seeds.
 *
 * Each photo goes through the normal upload pipeline once; a restaurant that
 * uses the same photo again gets a copy of the stored sizes (no re-encoding).
 */
class Photos
{
    /** @var array<string, array{folder: string, result: array<string, mixed>}> */
    private array $done = [];

    /** @var array<string, true> every photo key the seeders asked for */
    public array $used = [];

    public function __construct(private readonly ImageProcessor $images) {}

    public static function directory(): string
    {
        return config('menu.demo_photos_path') ?: database_path('seeders/demo-photos');
    }

    public static function path(string $key): string
    {
        return self::directory()."/{$key}.webp";
    }

    /** True when the real photo is on disk (otherwise a generated picture is used). */
    public static function has(string $key): bool
    {
        return is_file(self::path($key));
    }

    /** A Media row for the current tenant, or null when GD is missing. */
    public function media(Tenant $tenant, string $key, bool $banner = false): ?Media
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        $this->used[$key] = true;
        $folder = $tenant->id.'/demo-'.$key;
        $disk = Storage::disk('public');

        if (isset($this->done[$key])) {
            $from = $this->done[$key];
            foreach ($from['result']['variants'] as $file) {
                $disk->copy($from['folder'].'/'.$file, $folder.'/'.$file);
            }
            $result = $from['result'];
        } else {
            [$path, $mime, $temporary] = self::has($key)
                ? [self::path($key), 'image/webp', false]
                : [$this->generated($key, $banner), 'image/jpeg', true];
            $result = $this->images->process($path, $mime, 'public', $folder, $banner);
            if ($temporary) {
                @unlink($path);
            }
            $this->done[$key] = ['folder' => $folder, 'result' => $result];
        }

        return Media::create([...$result, 'disk' => 'public', 'base_path' => $folder, 'mime' => 'image/webp', 'bytes' => $this->bytes($folder, $result['variants'])]);
    }

    /** A simple round logo in the brand colour with a symbol (no text, no real brand). */
    public function logo(Tenant $tenant, string $hex, string $symbol): ?Media
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x') ?: [120, 60, 30];
        $img = imagecreatetruecolor(512, 512);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, (int) $r, (int) $g, (int) $b));
        $light = (int) imagecolorallocate($img, 250, 244, 236);
        $brand = (int) imagecolorallocate($img, (int) $r, (int) $g, (int) $b);
        imagesetthickness($img, 16);
        match ($symbol) {
            'bean' => (function () use ($img, $light, $brand) {
                imagefilledellipse($img, 256, 256, 260, 340, $light);
                imagearc($img, 256, 256, 90, 300, 290, 70, $brand);
            })(),
            'ring' => (function () use ($img, $light) {
                imageellipse($img, 256, 256, 330, 330, $light);
                imagefilledellipse($img, 256, 256, 60, 60, $light);
            })(),
            'slice' => imagefilledpolygon($img, [256, 400, 120, 140, 392, 140], $light),
            default => (function () use ($img, $light, $brand) { // wheat / drop
                imagefilledellipse($img, 256, 290, 220, 220, $light);
                imagefilledpolygon($img, [256, 90, 160, 250, 352, 250], $light);
                imagefilledellipse($img, 226, 300, 50, 50, $brand);
            })(),
        };
        $path = tempnam(sys_get_temp_dir(), 'logo').'.jpg';
        imagejpeg($img, $path, 92);
        $folder = $tenant->id.'/demo-logo';
        $result = $this->images->process($path, 'image/jpeg', 'public', $folder);
        @unlink($path);

        return Media::create([...$result, 'disk' => 'public', 'base_path' => $folder, 'mime' => 'image/jpeg', 'bytes' => $this->bytes($folder, $result['variants'])]);
    }

    /** A small warm "plate" picture, coloured by the key, for when the real photo isn't downloaded. */
    private function generated(string $key, bool $banner): string
    {
        $palette = [[196, 120, 60], [120, 70, 40], [90, 140, 70], [210, 170, 90], [160, 60, 50], [230, 190, 140], [70, 50, 40], [200, 100, 80]];
        [$r, $g, $b] = $palette[crc32($key) % count($palette)];
        [$w, $h] = $banner ? [800, 400] : [480, 320];
        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y += 4) {
            $k = $y / $h;
            imagefilledrectangle($img, 0, $y, $w - 1, $y + 3, (int) imagecolorallocate($img, (int) (245 - 30 * $k), (int) (238 - 30 * $k), (int) (228 - 30 * $k)));
        }
        imagefilledellipse($img, intdiv($w, 2), (int) ($h * 0.52), (int) ($w * 0.62), (int) ($h * 0.7), (int) imagecolorallocate($img, 250, 250, 248));
        imagefilledellipse($img, intdiv($w, 2), (int) ($h * 0.52), (int) ($w * 0.48), (int) ($h * 0.52), (int) imagecolorallocate($img, $r, $g, $b));
        $path = tempnam(sys_get_temp_dir(), 'demo').'.jpg';
        imagejpeg($img, $path, 85);

        return $path;
    }

    /** @param  array<int|string, string>  $variants */
    private function bytes(string $folder, array $variants): int
    {
        $disk = Storage::disk('public');

        return (int) array_sum(array_map(fn (string $f) => $disk->exists("{$folder}/{$f}") ? $disk->size("{$folder}/{$f}") : 0, $variants));
    }
}
