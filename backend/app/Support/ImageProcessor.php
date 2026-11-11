<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns one upload into the sizes the public menu uses (PLAN.md §3.3, §4.8),
 * with plain GD so it runs on any shared host (QUESTIONS #57):
 * - 320 / 640 / 800 / 1280 px wide WebP for `srcset` (800 fits most phones
 *   at full width, so the banner and big photos don't need 1280) (and AVIF too when the
 *   server's GD supports it — many shared hosts don't),
 * - 640 px JPEG for old browsers,
 * - 1200×630 JPEG for link previews (Open Graph),
 * plus the average colour and a tiny blurred preview (data URI under 300
 * bytes) to show inline while the photo loads. Re-encoding drops all metadata
 * (EXIF, GPS); phone photos are turned upright first.
 */
class ImageProcessor
{
    /** Longest side kept from the original. The browser already downscales to this. */
    public const MAX_SIDE = 2048;

    public const WIDTHS = [320, 640, 800, 1280];

    /** Strong compression (PLAN.md §15): photos are the heaviest part of a menu. */
    public const WEBP_QUALITY = 72;

    public const AVIF_QUALITY = 55;

    public const JPEG_QUALITY = 75;

    /**
     * The menu banner is the page's largest paint (LCP), so it is saved a
     * little lighter (owner's LCP budget < 1.2 s; QUESTIONS #114).
     */
    public const BANNER_WEBP_QUALITY = 64;

    private int $webpQuality = self::WEBP_QUALITY;

    /** Width of the inline preview; it is shown blurred, so tiny is enough. */
    private const PLACEHOLDER_WIDTH = 16;

    /**
     * @return array{width: int, height: int, variants: array<int|string, string>, dominant_color: string, placeholder: string}
     */
    public function process(string $path, string $mime, string $disk, string $folder, bool $banner = false): array
    {
        $this->webpQuality = $banner ? self::BANNER_WEBP_QUALITY : self::WEBP_QUALITY;
        $image = $this->load($path, $mime);
        $image = $this->orient($image, $path, $mime);
        $image = $this->fit($image, self::MAX_SIDE);

        $width = imagesx($image);
        $height = imagesy($image);
        $variants = [];

        foreach (self::WIDTHS as $target) {
            // Never enlarge; a small photo still gets every name.
            $scaled = $this->scaleToWidth($image, min($target, $width));
            $variants[(string) $target] = $this->store($scaled, 'webp', $disk, "{$folder}/{$target}.webp");
            if (self::supportsAvif()) {
                $variants["avif{$target}"] = $this->store($scaled, 'avif', $disk, "{$folder}/{$target}.avif");
            }
            if ($target === 640) {
                $variants['jpg'] = $this->store($scaled, 'jpeg', $disk, "{$folder}/640.jpg");
            }
        }
        $variants['og'] = $this->store($this->cover($image, 1200, 630), 'jpeg', $disk, "{$folder}/og.jpg");

        return [
            'width' => $width,
            'height' => $height,
            'variants' => array_map(fn (string $p) => basename($p), $variants),
            'dominant_color' => $this->averageColor($image),
            'placeholder' => $this->placeholder($image),
        ];
    }

    public static function supportsAvif(): bool
    {
        return function_exists('imageavif') && (bool) (gd_info()['AVIF Support'] ?? false);
    }

    /** A 16 px wide WebP as a data URI, shown blurred until the photo arrives. */
    private function placeholder(GdImage $image): string
    {
        $tiny = $this->resample($image, self::PLACEHOLDER_WIDTH, max(1, (int) round(imagesy($image) * self::PLACEHOLDER_WIDTH / imagesx($image))));
        ob_start();
        imagewebp($tiny, null, 30);

        return 'data:image/webp;base64,'.base64_encode((string) ob_get_clean());
    }

    private function load(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unreadable image.');
        }

        // Flatten transparency onto white (JPEG copies have no alpha).
        if ($mime !== 'image/jpeg') {
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $image = $flat;
        }

        return $image;
    }

    /** Phone cameras store "rotate me" in EXIF instead of rotating the pixels. */
    private function orient(GdImage $image, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function fit(GdImage $image, int $max): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) <= $max) {
            return $image;
        }

        return $w >= $h ? $this->scaleToWidth($image, $max) : $this->resample($image, (int) round($w * $max / $h), $max);
    }

    private function scaleToWidth(GdImage $image, int $width): GdImage
    {
        $w = imagesx($image);
        if ($width >= $w) {
            return $image;
        }

        return $this->resample($image, $width, max(1, (int) round(imagesy($image) * $width / $w)));
    }

    /** Fills exactly width×height, cropping the overflow around the centre. */
    private function cover(GdImage $image, int $width, int $height): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = max($width / $w, $height / $h);
        $cropW = (int) round($width / $scale);
        $cropH = (int) round($height / $scale);

        $out = imagecreatetruecolor($width, $height);
        imagecopyresampled($out, $image, 0, 0, (int) (($w - $cropW) / 2), (int) (($h - $cropH) / 2), $width, $height, $cropW, $cropH);

        return $out;
    }

    private function resample(GdImage $image, int $width, int $height): GdImage
    {
        $out = imagecreatetruecolor($width, $height);
        imagecopyresampled($out, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $out;
    }

    private function averageColor(GdImage $image): string
    {
        $pixel = imagecreatetruecolor(1, 1);
        imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
        $rgb = imagecolorat($pixel, 0, 0);

        return sprintf('#%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }

    private function store(GdImage $image, string $format, string $disk, string $path): string
    {
        ob_start();
        match ($format) {
            'webp' => imagewebp($image, null, $this->webpQuality),
            'avif' => imageavif($image, null, self::AVIF_QUALITY, 6),
            default => imagejpeg($image, null, self::JPEG_QUALITY),
        };
        Storage::disk($disk)->put($path, (string) ob_get_clean());

        return $path;
    }
}
