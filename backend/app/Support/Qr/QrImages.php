<?php

namespace App\Support\Qr;

use App\Models\QrCode as QrCodeModel;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR images for printing (chillerlan/php-qrcode, QUESTIONS #75). Settings
 * chosen for reliable scanning on any phone: plain lower-case https URL in
 * byte mode, error correction Q (25 % of the code may be dirty or torn),
 * the full 4-module quiet zone, black on white, no logo over the modules.
 */
class QrImages
{
    public static function url(QrCodeModel $code): string
    {
        return rtrim((string) config('app.url'), '/').'/q/'.$code->code;
    }

    /** @param array<string, mixed> $extra */
    private static function options(array $extra = []): QROptions
    {
        return new QROptions([
            'eccLevel' => EccLevel::Q,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
            'outputBase64' => false,
            ...$extra,
        ]);
    }

    public static function svg(string $url): string
    {
        return (string) (new QRCode(self::options([
            'outputInterface' => QRMarkupSVG::class,
            'drawLightModules' => false,
            'svgAddXmlHeader' => true,
            'connectPaths' => true,
        ])))->render($url);
    }

    public static function png(string $url, int $scale = 20): string
    {
        return (string) (new QRCode(self::options([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => $scale,
        ])))->render($url);
    }

    /** @return list<list<bool>> dark modules, quiet zone included */
    public static function matrix(string $url): array
    {
        $rows = (new QRCode(self::options()))->addByteSegment($url)->getQRMatrix()->getBooleanMatrix();

        return array_values(array_map('array_values', $rows));
    }
}
