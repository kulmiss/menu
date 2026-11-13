<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The platform logo at the top of every e-mail (owner, 2026-11-13). Stored as
 * a PNG at most 480 × 120 px on the public disk; without one, the e-mail
 * header shows the platform name.
 */
class EmailLogo
{
    public static function url(): ?string
    {
        $path = PlatformSettings::get('logo');

        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    public static function store(UploadedFile $file): string
    {
        $data = (string) file_get_contents((string) $file->getRealPath());
        $image = imagecreatefromstring($data);
        abort_if($image === false, 422, __('validation.image', ['attribute' => 'image']));
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, 480 / $w, 120 / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, (int) imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagepng($out, null, 9);
        $png = (string) ob_get_clean();

        $path = 'platform/logo-'.Str::lower(Str::random(10)).'.png';
        Storage::disk('public')->put($path, $png);
        self::forget();
        PlatformSettings::save(['logo' => $path]);

        return $path;
    }

    public static function forget(): void
    {
        $old = PlatformSettings::get('logo');
        if (is_string($old) && str_starts_with($old, 'platform/')) {
            Storage::disk('public')->delete($old);
        }
        PlatformSettings::save(['logo' => null]);
    }
}
