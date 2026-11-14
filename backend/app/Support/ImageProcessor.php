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

    /**
     * Upload limits checked from the file header BEFORE decoding (SECURITY_REPORT.md M6):
     * a tiny "decompression bomb" PNG of 30 000 × 30 000 px would otherwise make GD
     * try to allocate gigabytes and kill the PHP worker. 50 MP covers 50 MP phone modes.
     */
    public const MAX_PIXELS = 50_000_000;

    public const MAX_INPUT_SIDE = 16_384;

    /**
     * Width and height from the header, or null when the file is too big to
     * decode safely (pixels, side, or this server's PHP memory_limit).
     *
     * @return array{0: int, 1: int}|null
     */
    public static function safeSize(string $path): ?array
    {
        $info = @getimagesize($path);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return null;
        }
        [$w, $h] = [(int) $info[0], (int) $info[1]];
        if ($w > self::MAX_INPUT_SIDE || $h > self::MAX_INPUT_SIDE || $w * $h > self::MAX_PIXELS) {
            return null;
        }
        // GD keeps 4 bytes per pixel, plus a copy while flattening/resizing.
        $limit = self::memoryLimit();
        if ($limit > 0 && memory_get_usage(true) + $w * $h * 9 > $limit) {
            return null;
        }

        return [$w, $h];
    }

    private static function memoryLimit(): int
    {
        $v = trim((string) ini_get('memory_limit'));
        if ($v === '' || $v === '-1') {
            return 0;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

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
        $variants['og'] = $this->shareImage($image, $disk, "{$folder}/og.jpg");

        return [
            'width' => $width,
            'height' => $height,
            'variants' => array_map(fn (string $p) => basename($p), $variants),
            'dominant_color' => $this->averageColor($image),
            'placeholder' => $this->placeholder($image),
        ];
    }

    /**
     * Makes the sizes an older photo is missing (e.g. 800 px, added on
     * 2026-11-11) from the largest copy it already has; the original upload
     * isn't kept. Returns the new variant names (empty when nothing was missing).
     *
     * @param  array<int|string, string>  $variants  size => file name ('320' keys are ints in PHP)
     * @return array<int|string, string>
     */
    public function addMissing(string $disk, string $folder, array $variants, bool $banner = false): array
    {
        $avif = self::supportsAvif();
        $missing = array_filter(self::WIDTHS, fn (int $w) => ! array_key_exists($w, $variants) || ($avif && ! array_key_exists('avif'.$w, $variants)));
        $source = null;
        foreach (self::WIDTHS as $w) {
            $source = array_key_exists($w, $variants) ? $variants[$w] : $source; // the largest one wins
        }
        $needsOg = ! array_key_exists('og', $variants);
        if (($missing === [] && ! $needsOg) || $source === null) {
            return [];
        }
        $bytes = Storage::disk($disk)->get("{$folder}/{$source}");
        $image = $bytes === null ? false : @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            throw new RuntimeException("Unreadable image in {$folder}.");
        }
        $this->webpQuality = $banner ? self::BANNER_WEBP_QUALITY : self::WEBP_QUALITY;
        $added = [];
        // The link preview (1200 × 630 JPEG) for photos uploaded before it existed.
        if ($needsOg) {
            $added['og'] = basename($this->shareImage($image, $disk, "{$folder}/og.jpg"));
        }
        foreach ($missing as $target) {
            $scaled = $this->scaleToWidth($image, min($target, imagesx($image)));
            if (! array_key_exists($target, $variants)) {
                $added[$target] = basename($this->store($scaled, 'webp', $disk, "{$folder}/{$target}.webp"));
            }
            if ($avif && ! array_key_exists('avif'.$target, $variants)) {
                $added['avif'.$target] = basename($this->store($scaled, 'avif', $disk, "{$folder}/{$target}.avif"));
            }
        }

        return $added;
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
        if (self::safeSize($path) === null) {
            throw new ImageTooLarge('Image too large to decode safely.');
        }
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
    /** Largest file a link preview may be; WhatsApp skips bigger images (owner, 2026-11-12: ~300 KB). */
    public const SHARE_MAX_BYTES = 300 * 1024;

    /**
     * The link preview: 1200 × 630, centre crop, JPEG (every app reads it),
     * quality lowered step by step until it is under SHARE_MAX_BYTES.
     */
    public function shareImage(GdImage $image, string $disk, string $path): string
    {
        $card = $this->cover($image, 1200, 630);
        $bytes = '';
        foreach ([self::JPEG_QUALITY, 68, 60, 52, 45] as $quality) {
            ob_start();
            imageinterlace($card, true);
            imagejpeg($card, null, $quality);
            $bytes = (string) ob_get_clean();
            if (strlen($bytes) <= self::SHARE_MAX_BYTES) {
                break;
            }
        }
        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }

    /** Public wrapper for an uploaded file (Super Admin's default preview). */
    public function shareImageFrom(string $path, string $mime, string $disk, string $target): string
    {
        return $this->shareImage($this->orient($this->load($path, $mime), $path, $mime), $disk, $target);
    }

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
