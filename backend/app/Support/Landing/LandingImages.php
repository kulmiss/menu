<?php

namespace App\Support\Landing;

use App\Support\ImageProcessor;
use App\Support\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The pictures of the landing page (owner, 2026-11-17): the phone
 * screenshots in the hero and in "See it on your phone" (light and dark) and
 * the link preview. Built-in ones ship in public/landing; the Super Admin can
 * upload others (setting "landing_images"), and reset them.
 */
class LandingImages
{
    /** Slot => built-in file name (public/landing/{name}-{width}.{webp|avif}, or the file itself). */
    public const SLOTS = [
        'hero' => 'hero',
        'hero_dark' => 'hero-dark',
        'demo' => 'demo',
        'demo_dark' => 'demo-dark',
        'og' => 'og.jpg',
    ];

    private const FOLDER = 'landing';

    /**
     * A phone screenshot as srcset strings, or the link preview's address.
     *
     * @return array{webp: string, avif: string|null, src: string, custom: bool}
     */
    public static function phone(string $slot): array
    {
        $custom = self::saved()[$slot] ?? null;
        $widths = ImageProcessor::PHONE_WIDTHS;
        if ($custom !== null) {
            $disk = Storage::disk('public');
            $set = fn (string $ext) => implode(', ', array_map(fn (int $w) => $disk->url("{$custom['folder']}/{$w}.{$ext}")." {$w}w", $widths));

            return ['webp' => $set('webp'), 'avif' => $custom['avif'] ? $set('avif') : null, 'src' => $disk->url("{$custom['folder']}/600.webp"), 'custom' => true];
        }
        $name = self::SLOTS[$slot];
        $set = fn (string $ext) => implode(', ', array_map(fn (int $w) => asset("landing/{$name}-{$w}.{$ext}")." {$w}w", $widths));

        // AVIF only when those files exist (frontend/scripts/landing-screenshots.mjs writes them when GD can).
        $avif = is_file(public_path("landing/{$name}-600.avif"));

        return ['webp' => $set('webp'), 'avif' => $avif ? $set('avif') : null, 'src' => asset("landing/{$name}-600.webp"), 'custom' => false];
    }

    /**
     * The phone screenshot of a menu style's demo for the "Menu styles" strip
     * (frontend/scripts/landing-screenshots.mjs), or null while there is none.
     *
     * @return array{webp: string, avif: string|null, src: string}|null
     */
    public static function style(string $style): ?array
    {
        if (preg_match('/^[a-z]+$/', $style) !== 1 || ! is_file(public_path("landing/styles/{$style}-360.webp"))) {
            return null;
        }
        $set = fn (string $ext) => asset("landing/styles/{$style}-180.{$ext}").' 180w, '.asset("landing/styles/{$style}-360.{$ext}").' 360w';

        return [
            'webp' => $set('webp'),
            'avif' => is_file(public_path("landing/styles/{$style}-360.avif")) ? $set('avif') : null,
            'src' => asset("landing/styles/{$style}-180.webp"),
        ];
    }

    /**
     * Landing 2's style switcher: the style's phone screen for the hero phone, 1x and 2x
     * (WebP, at most 20 KB each), or null while there is none.
     *
     * @return array{src: string, srcset: string}|null
     */
    public static function switchShot(string $style): ?array
    {
        if (preg_match('/^[a-z]+$/', $style) !== 1 || ! is_file(public_path("landing/switch/{$style}-1x.webp")) || ! is_file(public_path("landing/switch/{$style}-2x.webp"))) {
            return null;
        }

        return [
            'src' => asset("landing/switch/{$style}-1x.webp"),
            'srcset' => asset("landing/switch/{$style}-1x.webp").' 1x, '.asset("landing/switch/{$style}-2x.webp").' 2x',
        ];
    }

    /** @return array{url: string, custom: bool} */
    public static function og(): array
    {
        $custom = self::saved()['og'] ?? null;

        return $custom !== null
            ? ['url' => Storage::disk('public')->url($custom['file']), 'custom' => true]
            : ['url' => asset('landing/og.jpg'), 'custom' => false];
    }

    public static function store(string $slot, UploadedFile $file, ImageProcessor $images): void
    {
        $id = Str::lower(Str::random(10));
        if ($slot === 'og') {
            $path = self::FOLDER."/og-{$id}.jpg";
            $images->shareImageFrom((string) $file->getRealPath(), (string) $file->getMimeType(), 'public', $path);
            $entry = ['file' => $path];
        } else {
            $folder = self::FOLDER."/{$slot}-{$id}";
            $avif = $images->phoneShot((string) $file->getRealPath(), (string) $file->getMimeType(), 'public', $folder);
            $entry = ['folder' => $folder, 'avif' => $avif];
        }
        self::forget($slot);
        PlatformSettings::save(['landing_images' => [...self::saved(), $slot => $entry]]);
    }

    /** Back to the built-in picture; the uploaded files are deleted. */
    public static function forget(string $slot): void
    {
        $saved = self::saved();
        $old = $saved[$slot] ?? null;
        if ($old !== null) {
            $disk = Storage::disk('public');
            isset($old['file']) ? $disk->delete($old['file']) : $disk->deleteDirectory($old['folder']);
        }
        unset($saved[$slot]);
        PlatformSettings::save(['landing_images' => $saved]);
    }

    /** @return array<string, array{folder?: string, avif?: bool, file?: string}> */
    private static function saved(): array
    {
        $saved = PlatformSettings::get('landing_images');
        $out = [];
        foreach (is_array($saved) ? $saved : [] as $slot => $entry) {
            $ok = isset(self::SLOTS[$slot]) && is_array($entry) && (
                ($slot === 'og' && is_string($entry['file'] ?? null) && str_starts_with($entry['file'], self::FOLDER.'/'))
                || ($slot !== 'og' && is_string($entry['folder'] ?? null) && str_starts_with($entry['folder'], self::FOLDER.'/'))
            );
            if ($ok) {
                $out[$slot] = $entry;
            }
        }

        return $out;
    }
}
