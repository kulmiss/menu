<?php

namespace App\Support\Print;

use App\Support\Qr\TableCards;

/**
 * Printable posters, table tents, stickers and window signs (owner,
 * 2026-11-14, QUESTIONS #179). Vector PDF (FPDF core fonts, QR as squares),
 * exact paper sizes; posters and the window sign can add 3 mm bleed on every
 * side for a print shop. Three designs × light/dark; the QR code itself is
 * always black on white so every phone can read it.
 */
class PrintMaterials
{
    /** material => [page width, page height, orientation] in mm */
    public const PAGES = [
        'tent' => [210.0, 297.0],
        'poster_a5' => [148.0, 210.0],
        'poster_a4' => [210.0, 297.0],
        'window' => [297.0, 210.0],
        'sticker_round' => [210.0, 297.0],
        'sticker_square' => [210.0, 297.0],
    ];

    public const DESIGNS = ['classic', 'bold', 'minimal'];

    public const BLEED = 3.0;

    private const STICKER = 60.0;

    private const GAP = 6.0;

    private Canvas $pdf;

    /** @var array{bg: list<int>, text: list<int>, muted: list<int>, accent: list<int>, band: list<int>|null, border: list<int>|null} */
    private array $c;

    /**
     * @param  list<array{title: string, subtitle: string|null, label: string|null, url: string}>  $codes
     * @param  array{material: string, design: string, theme: string, headline: string, scan: string, bleed: bool, colour: string, logo: string|null, branding: string|null}  $o
     */
    public function __construct(private array $codes, private array $o)
    {
        [$w, $h] = self::PAGES[$o['material']];
        $bleed = $this->bleed();
        $this->pdf = new Canvas($w > $h ? 'L' : 'P', 'mm', [min($w, $h) + 2 * $bleed, max($w, $h) + 2 * $bleed]);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->SetCreator('Menu');
        $this->pdf->SetTitle(TableCards::text($o['headline'].' - '.($codes[0]['title'] ?? '')));
        $this->c = self::palette($o['design'], $o['theme'], self::rgb($o['colour']));
    }

    /** Sizes with bleed: only posters and the window sign; tents and stickers are cut by hand. */
    public function bleed(): float
    {
        return $this->o['bleed'] && in_array($this->o['material'], ['poster_a5', 'poster_a4', 'window'], true) ? self::BLEED : 0.0;
    }

    public function render(): string
    {
        match ($this->o['material']) {
            'tent' => $this->tents(),
            'sticker_round', 'sticker_square' => $this->stickers($this->o['material'] === 'sticker_round'),
            'window' => $this->pages(fn (array $code, float $b) => $this->window($b, $b, 297, 210, $code, $b)),
            default => $this->pages(function (array $code, float $b) {
                [$w, $h] = self::PAGES[$this->o['material']];
                $this->portrait($b, $b, $w, $h, $code, $b);
            }),
        };

        return $this->pdf->Output('S');
    }

    /** @param callable(array{title: string, subtitle: string|null, label: string|null, url: string}, float): void $draw */
    private function pages(callable $draw): void
    {
        foreach ($this->codes as $code) {
            $this->pdf->AddPage();
            $draw($code, $this->bleed());
        }
    }

    /** Two tents per A4 page: fold along the middle, cut down the centre; the top face is upside down so both sides read upright. */
    private function tents(): void
    {
        $codes = count($this->codes) === 1 ? [$this->codes[0], $this->codes[0]] : $this->codes;
        foreach (array_chunk($codes, 2) as $pair) {
            $this->pdf->AddPage();
            foreach ($pair as $col => $code) {
                $x = $col * 105.0;
                $this->portrait($x, 148.5, 105, 148.5, $code, 0);
                $this->pdf->rotate(180, $x + 52.5, 74.25);
                $this->portrait($x, 0, 105, 148.5, $code, 0);
                $this->pdf->rotate(0, 0, 0);
            }
            $this->pdf->SetLineWidth(0.25);
            $this->pdf->SetDrawColor(150, 150, 150);
            $this->pdf->dash(2, 1.5);
            $this->pdf->Line(0, 148.5, 210, 148.5); // fold
            $this->pdf->dash(0);
            $this->pdf->Line(105, 0, 105, 297); // cut
        }
    }

    /** Stickers 60 mm, 3 × 4 per A4 page with cut lines. One code fills the sheet. */
    private function stickers(bool $round): void
    {
        $per = 12;
        $codes = count($this->codes) === 1 ? array_fill(0, $per, $this->codes[0]) : $this->codes;
        $d = self::STICKER;
        $left = (210 - 3 * $d - 2 * self::GAP) / 2;
        $top = (297 - 4 * $d - 3 * self::GAP) / 2;
        foreach (array_chunk($codes, $per) as $sheet) {
            $this->pdf->AddPage();
            foreach ($sheet as $i => $code) {
                $x = $left + ($i % 3) * ($d + self::GAP);
                $y = $top + intdiv($i, 3) * ($d + self::GAP);
                $this->sticker($x, $y, $d, $code, $round);
            }
        }
    }

    /** @param array{title: string, subtitle: string|null, label: string|null, url: string} $code */
    private function sticker(float $x, float $y, float $d, array $code, bool $round): void
    {
        $this->pdf->SetFillColor(...$this->c['bg']);
        $round ? $this->pdf->circle($x + $d / 2, $y + $d / 2, $d / 2, 'F') : $this->pdf->roundedRect($x, $y, $d, $d, 4, 'F');
        if ($this->c['band'] && ! $round) {
            $this->pdf->SetFillColor(...$this->c['band']);
            $this->pdf->Rect($x + 4, $y, $d - 8, 2.2, 'F');
        }
        $inner = $round ? $d * 0.6 : $d - 8;
        $this->lines($x + ($d - $inner) / 2, $y + ($round ? 6.5 : 4.5), $inner, $this->o['headline'], 'B', 10, 6.5, 1, $this->c['text']);
        $qr = $round ? $d * 0.5 : $d * 0.6;
        $this->qrCard($x + ($d - $qr) / 2, $y + ($round ? 13 : 11), $qr, $code['url'], 1.2);
        $below = $y + ($round ? 13 : 11) + $qr + 2;
        $this->line($x + 4, $below, $d - 8, $code['label'] ?? $code['title'], 'B', 7.5, $this->c['text'], 3.5);
        if ($this->o['branding']) {
            $this->line($x + 4, $below + 3.6, $d - 8, $this->o['branding'], '', 4.5, $this->c['muted'], 2.2);
        }
        // Cut line: thin grey outline exactly on the edge.
        $this->pdf->SetDrawColor(160, 160, 160);
        $this->pdf->SetLineWidth(0.2);
        $round ? $this->pdf->circle($x + $d / 2, $y + $d / 2, $d / 2) : $this->pdf->roundedRect($x, $y, $d, $d, 4, 'D');
    }

    /**
     * A portrait face: tent side, A5 or A4 poster.
     *
     * @param  array{title: string, subtitle: string|null, label: string|null, url: string}  $code
     */
    private function portrait(float $x, float $y, float $w, float $h, array $code, float $bleed): void
    {
        $s = min($w / 105, $h / 148.5);
        $pad = max(8.0, 9 * $s);
        $this->background($x, $y, $w, $h, $bleed, 5 * $s);
        if ($this->o['design'] === 'minimal') {
            $this->pdf->SetDrawColor(...$this->c['muted']);
            $this->pdf->SetLineWidth(0.25);
            $this->pdf->Rect($x + 5 * $s, $y + 5 * $s, $w - 10 * $s, $h - 10 * $s);
        }
        $cy = $y + $pad + ($this->c['band'] ? 4 * $s : 0);
        $cy = $this->logo($x + $w / 2, $cy, 16 * $s);
        $this->line($x + $pad, $cy, $w - 2 * $pad, $code['title'], 'B', 12 * $s, $this->c['text'], 6 * $s);
        $cy += 6 * $s;
        if ($code['subtitle']) {
            $this->line($x + $pad, $cy, $w - 2 * $pad, $code['subtitle'], '', 9 * $s, $this->c['muted'], 4.5 * $s);
            $cy += 4.5 * $s;
        }
        $cy += 2 * $s;
        $cy = $this->lines($x + $pad, $cy, $w - 2 * $pad, $this->o['headline'], 'B', 24 * $s, 13 * $s, 2, $this->o['design'] === 'bold' ? $this->c['text'] : $this->c['accent']);

        // Footer from the bottom up: menu by, address, then label and scan hint above the code.
        $bottom = $y + $h - $pad;
        $foot = [];
        if ($this->o['branding']) {
            $foot[] = [$this->o['branding'], 6 * $s];
        }
        $foot[] = [preg_replace('#^https?://#', '', $code['url']) ?? '', 6.5 * $s];
        foreach ($foot as [$text, $size]) {
            $bottom -= $size * 0.5;
            $this->line($x + $pad, $bottom, $w - 2 * $pad, $text, '', $size, $this->c['muted'], $size * 0.5);
        }
        $bottom -= 2 * $s;
        if ($code['label']) {
            $bottom -= 8 * $s;
            $this->line($x + $pad, $bottom, $w - 2 * $pad, $code['label'], 'B', 17 * $s, $this->c['text'], 8 * $s);
        }
        $bottom -= 5 * $s;
        $this->line($x + $pad, $bottom, $w - 2 * $pad, $this->o['scan'], '', 9.5 * $s, $this->c['muted'], 5 * $s);

        $room = $bottom - 3 * $s - ($cy + 3 * $s);
        $size = min($w * 0.62, $room);
        $this->qrCard($x + ($w - $size) / 2, $cy + 3 * $s + ($room - $size) / 2, $size, $code['url'], 3 * $s);
    }

    /**
     * Window sign: A4 landscape, words on the left, a big code on the right.
     *
     * @param  array{title: string, subtitle: string|null, label: string|null, url: string}  $code
     */
    private function window(float $x, float $y, float $w, float $h, array $code, float $bleed): void
    {
        $pad = 14.0;
        $this->background($x, $y, $w, $h, $bleed, 6);
        if ($this->o['design'] === 'minimal') {
            $this->pdf->SetDrawColor(...$this->c['muted']);
            $this->pdf->SetLineWidth(0.3);
            $this->pdf->Rect($x + 6, $y + 6, $w - 12, $h - 12);
        }
        $size = $h - 2 * $pad - 16;
        $qx = $x + $w - $pad - $size;
        $colW = $qx - $x - 2 * $pad;
        $cy = $y + $pad + 10;
        $logo = $this->logoFits() ? 28.0 : 0.0;
        if ($logo > 0) {
            $this->logo($x + $pad + $logo / 2, $cy, $logo);
            $cy += $logo + 6;
        }
        $this->line($x + $pad, $cy, $colW, $code['title'], 'B', 20, $this->c['text'], 9, 'L');
        $cy += 10;
        if ($code['subtitle']) {
            $this->line($x + $pad, $cy, $colW, $code['subtitle'], '', 13, $this->c['muted'], 6, 'L');
            $cy += 7;
        }
        $cy += 4;
        $cy = $this->lines($x + $pad, $cy, $colW, $this->o['headline'], 'B', 46, 24, 3, $this->o['design'] === 'bold' ? $this->c['text'] : $this->c['accent'], 'L');
        $cy += 3;
        $cy = $this->lines($x + $pad, $cy, $colW, $this->o['scan'], '', 15, 11, 2, $this->c['muted'], 'L');
        if ($code['label']) {
            $this->line($x + $pad, $cy + 3, $colW, $code['label'], 'B', 20, $this->c['text'], 9, 'L');
        }
        $foot = $y + $h - $pad;
        if ($this->o['branding']) {
            $this->line($x + $pad, $foot - 4, $colW, $this->o['branding'], '', 8, $this->c['muted'], 4, 'L');
            $foot -= 5;
        }
        $this->line($x + $pad, $foot - 4, $colW, preg_replace('#^https?://#', '', $code['url']) ?? '', '', 9, $this->c['muted'], 4, 'L');
        $this->qrCard($qx, $y + ($h - $size) / 2, $size, $code['url'], 6);
    }

    private function background(float $x, float $y, float $w, float $h, float $bleed, float $band): void
    {
        $this->pdf->SetFillColor(...$this->c['bg']);
        $this->pdf->Rect($x - $bleed, $y - $bleed, $w + 2 * $bleed, $h + 2 * $bleed, 'F');
        if ($this->c['band']) {
            $this->pdf->SetFillColor(...$this->c['band']);
            $this->pdf->Rect($x - $bleed, $y - $bleed, $w + 2 * $bleed, $band + $bleed, 'F');
        }
    }

    /** White card around the code (its quiet zone grows a little). */
    private function qrCard(float $x, float $y, float $size, string $url, float $pad): void
    {
        $this->pdf->SetFillColor(255, 255, 255);
        if ($this->c['border']) {
            $this->pdf->SetDrawColor(...$this->c['border']);
            $this->pdf->SetLineWidth(0.5);
            $this->pdf->roundedRect($x - $pad, $y - $pad, $size + 2 * $pad, $size + 2 * $pad, $pad * 1.2, 'FD');
        } else {
            $this->pdf->roundedRect($x - $pad, $y - $pad, $size + 2 * $pad, $size + 2 * $pad, $pad * 1.2, 'F');
        }
        TableCards::qr($this->pdf, $x, $y, $size, $url);
    }

    private function logoFits(): bool
    {
        return $this->o['logo'] !== null && is_file($this->o['logo']);
    }

    /** The logo centred at $cx, on a white tile when the background is not white; returns the next y. */
    private function logo(float $cx, float $y, float $size): float
    {
        if (! $this->logoFits()) {
            return $y;
        }
        $file = (string) $this->o['logo'];
        [$iw, $ih] = getimagesize($file) ?: [1, 1];
        $ratio = $iw / max(1, $ih);
        $w = $ratio >= 1 ? $size : $size * $ratio;
        $h = $ratio >= 1 ? $size / $ratio : $size;
        if ($this->c['bg'] !== [255, 255, 255]) {
            $this->pdf->SetFillColor(255, 255, 255);
            $this->pdf->roundedRect($cx - $size / 2 - 1.5, $y - 1.5, $size + 3, $size + 3, 2.5, 'F');
        }
        $this->pdf->Image($file, $cx - $w / 2, $y + ($size - $h) / 2, $w, $h);

        return $y + $size + 4;
    }

    /** @param list<int> $colour */
    private function line(float $x, float $y, float $w, string $text, string $style, float $size, array $colour, float $height, string $align = 'C'): void
    {
        $this->pdf->SetFont('Helvetica', $style, $size);
        $this->pdf->SetTextColor(...$colour);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($w, $height, TableCards::fit($this->pdf, $text, $w), 0, 0, $align);
    }

    /**
     * Text that shrinks from $max to $min points until it fits in $lines lines.
     *
     * @param  list<int>  $colour
     */
    private function lines(float $x, float $y, float $w, string $text, string $style, float $max, float $min, int $lines, array $colour, string $align = 'C'): float
    {
        $words = preg_split('/\s+/', trim(TableCards::text($text))) ?: [];
        for ($size = $max; ; $size -= 0.5) {
            $this->pdf->SetFont('Helvetica', $style, $size);
            $rows = [''];
            foreach ($words as $word) {
                $try = trim(end($rows).' '.$word);
                if ($this->pdf->GetStringWidth($try) <= $w || end($rows) === '') {
                    $rows[count($rows) - 1] = $try;
                } else {
                    $rows[] = $word;
                }
            }
            $fits = count($rows) <= $lines && max(array_map(fn ($r) => $this->pdf->GetStringWidth($r), $rows)) <= $w;
            if ($fits || $size <= $min) {
                break;
            }
        }
        $rows = array_slice($rows, 0, $lines);
        $height = $size * 0.42;
        $this->pdf->SetTextColor(...$colour);
        foreach ($rows as $i => $row) {
            $this->pdf->SetXY($x, $y + $i * $height);
            $this->pdf->Cell($w, $height, $this->pdf->GetStringWidth($row) > $w ? TableCards::fit($this->pdf, $row, $w) : $row, 0, 0, $align);
        }

        return $y + count($rows) * $height;
    }

    /**
     * @param  list<int>  $brand
     * @return array{bg: list<int>, text: list<int>, muted: list<int>, accent: list<int>, band: list<int>|null, border: list<int>|null}
     */
    public static function palette(string $design, string $theme, array $brand): array
    {
        $dark = $theme === 'dark';
        $ink = [20, 18, 16];
        $paper = [255, 255, 255];
        $night = [22, 20, 18];
        $onBrand = self::luminance($brand) > 0.45 ? $ink : $paper;

        return match ($design) {
            'bold' => $dark
                ? ['bg' => $night, 'text' => [245, 242, 238], 'muted' => [190, 184, 178], 'accent' => $brand, 'band' => $brand, 'border' => null]
                : ['bg' => $brand, 'text' => $onBrand, 'muted' => $onBrand, 'accent' => $onBrand, 'band' => null, 'border' => null],
            'minimal' => $dark
                ? ['bg' => [12, 12, 12], 'text' => [245, 245, 245], 'muted' => [170, 170, 170], 'accent' => [245, 245, 245], 'band' => null, 'border' => null]
                : ['bg' => $paper, 'text' => $ink, 'muted' => [110, 106, 102], 'accent' => $ink, 'band' => null, 'border' => [200, 196, 192]],
            default => $dark
                ? ['bg' => $night, 'text' => [245, 242, 238], 'muted' => [180, 174, 168], 'accent' => self::luminance($brand) < 0.12 ? [245, 242, 238] : $brand, 'band' => $brand, 'border' => $brand]
                : ['bg' => $paper, 'text' => $ink, 'muted' => [105, 100, 95], 'accent' => self::luminance($brand) > 0.6 ? $ink : $brand, 'band' => $brand, 'border' => $brand],
        };
    }

    /** @return list<int> */
    public static function rgb(string $hex): array
    {
        $v = sscanf($hex, '#%02x%02x%02x');

        return is_array($v) && count(array_filter($v, 'is_int')) === 3 ? array_map('intval', $v) : [20, 18, 16];
    }

    /** @param list<int> $rgb */
    private static function luminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(function (int $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
