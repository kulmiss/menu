<?php

namespace App\Console\Commands;

use Database\Seeders\Demo\Photos;
use GdImage;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads the demo photos listed in database/seeders/demo-photos/photos.json
 * (Unsplash License / Pexels License only) and stores them as WebP, at most
 * 1280 px (banners) or 1080 px (dishes), quality 75 — lowered a little if the
 * whole set would be over the budget (8 MB). Writes CREDITS.md from the list.
 * Originals are cached in storage/app/demo-photo-cache so re-encoding
 * doesn't download again. Commit the .webp files and CREDITS.md afterwards.
 */
class DownloadDemoPhotos extends Command
{
    protected $signature = 'demo:photos
        {--force : Download and encode every photo again}
        {--only= : Comma-separated photo keys}
        {--budget=8 : Largest total size of all photos, in MB}
        {--credits : Only rewrite CREDITS.md}';

    protected $description = 'Download the demo photos (Unsplash/Pexels licence) for the demo seeders';

    public const BANNER_SIDE = 1280;

    public const PHOTO_SIDE = 1080;

    public const QUALITY = 75;

    public const MIN_QUALITY = 60;

    public function handle(): int
    {
        $photos = self::manifest();
        File::put(Photos::directory().'/CREDITS.md', self::credits($photos));
        if ($this->option('credits')) {
            $this->info('CREDITS.md written.');

            return self::SUCCESS;
        }
        if (! function_exists('imagewebp')) {
            $this->error('PHP GD with WebP support is needed (enable the gd extension).');

            return self::FAILURE;
        }
        $only = array_filter(explode(',', (string) $this->option('only')));
        $todo = array_values(array_filter($photos, fn (array $p) => ($only === [] || in_array($p['key'], $only, true))
            && ($this->option('force') || ! Photos::has($p['key']))));
        File::ensureDirectoryExists(self::cache());

        $failed = [];
        $bar = $this->output->createProgressBar(count($todo));
        foreach ($todo as $photo) {
            try {
                $this->original($photo);
                $this->encode($photo, self::QUALITY);
            } catch (Throwable $e) {
                $failed[$photo['key']] = $e->getMessage();
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // Keep the whole set within the budget by lowering the quality a little.
        $budget = (float) $this->option('budget') * 1024 * 1024;
        for ($quality = self::QUALITY - 4; $this->total($photos) > $budget && $quality >= self::MIN_QUALITY; $quality -= 4) {
            $this->line("Over the budget: encoding again at quality {$quality}…");
            foreach ($photos as $photo) {
                if (is_file(self::cache()."/{$photo['key']}.jpg")) {
                    $this->encode($photo, $quality);
                }
            }
        }

        $have = count(array_filter($photos, fn (array $p) => Photos::has($p['key'])));
        $this->info(sprintf('%d of %d photos ready, %.1f MB in %s', $have, count($photos), $this->total($photos) / 1048576, Photos::directory()));
        foreach ($failed as $key => $reason) {
            $this->warn("Not downloaded: {$key} — {$reason}");
        }
        if ($failed !== []) {
            $this->line('The seeders use a generated picture for these. Run the command again later, or replace the entry in photos.json.');
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<array{key: string, source: string, id: string, page: string, photographer: string, profile: string|null, licence: string, banner: bool}> */
    public static function manifest(): array
    {
        return json_decode((string) file_get_contents(Photos::directory().'/photos.json'), true)['photos'];
    }

    /** @param  list<array{key: string, source: string, id: string, page: string, photographer: string, profile: string|null, licence: string, banner: bool}>  $photos */
    public static function credits(array $photos): string
    {
        $lines = [
            '# Demo photo credits',
            '',
            'Photos used by the demo seeders (`DemoSeeder`). Every photo is free for commercial use under the',
            '[Unsplash License](https://unsplash.com/license) or the [Pexels License](https://www.pexels.com/license/);',
            'credit is not required but given here. Download them with `php artisan demo:photos`',
            '(or `scripts/download-demo-photos.ps1` on Windows). This file is written from `photos.json`.',
            '',
            '| File | Source | Photographer | Licence |',
            '|---|---|---|---|',
        ];
        foreach ($photos as $p) {
            $who = $p['profile'] ? "[{$p['photographer']}]({$p['profile']})" : $p['photographer'];
            $lines[] = "| `{$p['key']}.webp` | [{$p['source']} {$p['id']}]({$p['page']}) | {$who} | {$p['licence']} |";
        }

        return implode("\n", $lines)."\n";
    }

    private static function cache(): string
    {
        return storage_path('app/demo-photo-cache');
    }

    /** @param  array{key: string, source: string, id: string, photographer: string}  $photo */
    private function original(array $photo): void
    {
        $file = self::cache()."/{$photo['key']}.jpg";
        if (is_file($file) && ! $this->option('force')) {
            return;
        }
        $urls = [];
        if ($photo['source'] === 'unsplash') {
            try {
                $urls[] = $this->unsplashImage($photo['id']);
            } catch (Throwable) {
                // Let the client follow the redirects itself (full-size file, resized below).
                $urls[] = "https://unsplash.com/photos/{$photo['id']}/download?force=true";
            }
        } else {
            $urls[] = "https://images.pexels.com/photos/{$photo['id']}/pexels-photo-{$photo['id']}.jpeg?auto=compress&cs=tinysrgb&w=1600";
            $urls[] = "https://images.pexels.com/photos/{$photo['id']}/pexels-{$photo['photographer']}-{$photo['id']}.jpg?auto=compress&cs=tinysrgb&w=1600";
        }
        $status = 0;
        foreach ($urls as $url) {
            $response = self::client()->get($url);
            if ($response->successful() && str_starts_with((string) $response->header('Content-Type'), 'image/') && strlen($response->body()) > 5000) {
                File::put($file, $response->body());

                return;
            }
            $status = $response->status();
        }
        throw new \RuntimeException("download failed (HTTP {$status})");
    }

    /**
     * The download link redirects (sometimes with a relative address, sometimes
     * more than once) to the file on images.unsplash.com; follow it and ask that
     * one for a 1600 px JPEG.
     */
    private function unsplashImage(string $id): string
    {
        $url = "https://unsplash.com/photos/{$id}/download?force=true";
        for ($hop = 0; $hop < 5; $hop++) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            if (str_ends_with($host, 'images.unsplash.com')) {
                return strtok($url, '?').'?w=1600&fit=max&fm=jpg&q=85';
            }
            $response = self::client(follow: false)->get($url);
            $location = $response->header('Location');
            if ($location === '') {
                throw new \RuntimeException("Unsplash answered {$response->status()} without a redirect");
            }
            $url = self::absolute($location, $url);
        }
        throw new \RuntimeException('too many redirects from Unsplash');
    }

    /**
     * A fresh client for every request: withOptions() changes the client it is
     * called on, so one shared client that once turned redirects off never
     * followed a 301/302/307/308 again ("download failed (HTTP 307)").
     */
    public static function client(bool $follow = true): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => 'MenuDemoPhotos/1.0'])->timeout(60)->withOptions([
            'allow_redirects' => $follow ? ['max' => 5, 'strict' => true, 'protocols' => ['https', 'http']] : false,
        ]);
    }

    /** "/photos/x" or "//host/x" relative to the page it came from. */
    public static function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $scheme = (string) parse_url($base, PHP_URL_SCHEME);
        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }
        $root = $scheme.'://'.parse_url($base, PHP_URL_HOST);

        return str_starts_with($location, '/') ? $root.$location : $root.'/'.ltrim($location, './');
    }

    /** @param  array{key: string, banner: bool}  $photo */
    private function encode(array $photo, int $quality): void
    {
        $image = @imagecreatefromstring((string) file_get_contents(self::cache()."/{$photo['key']}.jpg"));
        if (! $image instanceof GdImage) {
            throw new \RuntimeException('not a readable image');
        }
        $side = $photo['banner'] ? self::BANNER_SIDE : self::PHOTO_SIDE;
        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, $side / max($w, $h));
        if ($scale < 1) {
            $resized = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
            imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $w, $h);
            $image = $resized;
        }
        imagewebp($image, Photos::path($photo['key']), $quality);
    }

    /** @param  list<array{key: string}>  $photos */
    private function total(array $photos): int
    {
        return (int) array_sum(array_map(fn (array $p) => Photos::has($p['key']) ? filesize(Photos::path($p['key'])) : 0, $photos));
    }
}
