<?php

namespace App\Support\PublicMenu;

use App\Support\ImageProcessor;
use App\Support\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The picture a chat app shows when the menu link is shared (owner,
 * 2026-11-12): the restaurant's banner (its 1200 × 630 JPEG), else the
 * platform's default that Super Admin uploaded, else the built-in one.
 * Every address is absolute and based on APP_URL, with a version so apps
 * fetch the new picture after a change. The menu page never loads it.
 */
class ShareImage
{
    /** Ships with the platform (public/images/share-default.jpg). */
    public const BUILT_IN = 'images/share-default.jpg';

    /** Folder for Super Admin's default on the public media disk. */
    private const FOLDER = 'platform';

    /**
     * @param  array<string, mixed>|null  $cover  PageTheme cover (its 'og' is the banner's preview)
     * @return array{url: string, width: int, height: int, type: string}
     */
    public static function for(?array $cover): array
    {
        $url = $cover['og'] ?? null;
        if ($url === null) {
            $custom = PlatformSettings::get('share_image');
            $url = is_string($custom) && $custom !== '' ? Storage::disk('public')->url($custom) : self::builtIn();
        }

        return ['url' => self::absolute($url), 'width' => 1200, 'height' => 630, 'type' => 'image/jpeg'];
    }

    /** "/demo/piassa" or a relative media address → https://… on APP_URL. */
    public static function absolute(string $pathOrUrl): string
    {
        if (preg_match('#^https?://#', $pathOrUrl) === 1) {
            return $pathOrUrl;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($pathOrUrl, '/');
    }

    /** Super Admin's upload becomes the default preview; the previous one is removed. */
    public static function store(UploadedFile $file, ImageProcessor $images): string
    {
        $path = self::FOLDER.'/share-'.Str::lower(Str::random(10)).'.jpg';
        $images->shareImageFrom((string) $file->getRealPath(), (string) $file->getMimeType(), 'public', $path);
        self::forget();
        PlatformSettings::save(['share_image' => $path]);

        return $path;
    }

    /** Back to the built-in picture. */
    public static function forget(): void
    {
        $old = PlatformSettings::get('share_image');
        if (is_string($old) && str_starts_with($old, self::FOLDER.'/')) {
            Storage::disk('public')->delete($old);
        }
        PlatformSettings::save(['share_image' => null]);
    }

    private static function builtIn(): string
    {
        $file = public_path(self::BUILT_IN);

        return self::BUILT_IN.(File::exists($file) ? '?v='.substr(md5_file($file) ?: '', 0, 8) : '');
    }
}
