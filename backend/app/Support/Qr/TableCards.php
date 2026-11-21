<?php

namespace App\Support\Qr;

use App\Support\Pdf\Document;

/**
 * Printable table cards (A4, four A6 cards per page, with cut marks) and
 * single-code cards, drawn as vector squares so they stay sharp at any size.
 * Uses App\Support\Pdf\Document (any language; see QUESTIONS #205).
 */
class TableCards
{
    private const W = 105.0;

    private const H = 148.5;

    /**
     * @param  list<array{title: string, subtitle: string|null, label: string|null, url: string}>  $cards
     */
    public static function pdf(array $cards, string $colour, bool $sheet = true): string
    {
        $pdf = new Document('P', 'mm', $sheet ? 'A4' : [self::W, self::H]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCreator('Menu');
        $pdf->SetTitle(self::text($cards[0]['title'] ?? 'QR codes'));

        foreach ($cards as $i => $card) {
            $slot = $sheet ? $i % 4 : 0;
            if ($slot === 0) {
                $pdf->AddPage();
                if ($sheet) {
                    self::cutMarks($pdf);
                }
            }
            $x = $sheet ? ($slot % 2) * self::W : 0.0;
            $y = $sheet ? intdiv($slot, 2) * self::H : 0.0;
            self::card($pdf, $x, $y, $card, $colour);
        }

        return $pdf->Output('S');
    }

    /** @param array{title: string, subtitle: string|null, label: string|null, url: string} $card */
    private static function card(Document $pdf, float $x, float $y, array $card, string $colour): void
    {
        [$r, $g, $b] = sscanf($colour, '#%02x%02x%02x') ?: [0, 0, 0];
        $pdf->SetFillColor((int) $r, (int) $g, (int) $b);
        $pdf->Rect($x + 8, $y + 8, self::W - 16, 3, 'F');

        $pdf->SetTextColor(20, 18, 16);
        $pdf->SetFont('Helvetica', 'B', 17);
        $pdf->SetXY($x + 8, $y + 15);
        $pdf->Cell(self::W - 16, 8, self::fit($pdf, $card['title'], self::W - 16), 0, 2, 'C');
        if ($card['subtitle']) {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->Cell(self::W - 16, 6, self::fit($pdf, $card['subtitle'], self::W - 16), 0, 2, 'C');
        }

        // The code: 70 mm wide including its quiet zone, black on white.
        $size = 70.0;
        $left = $x + (self::W - $size) / 2;
        $top = $y + 33;
        self::qr($pdf, $left, $top, $size, $card['url']);

        $pdf->SetXY($x + 8, $top + $size + 3);
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(self::W - 16, 6, self::text(__('qr.scan')), 0, 2, 'C');
        if ($card['label']) {
            $pdf->SetFont('Helvetica', 'B', 22);
            $pdf->Cell(self::W - 16, 11, self::fit($pdf, $card['label'], self::W - 16), 0, 2, 'C');
        }
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(110, 104, 98);
        $pdf->SetXY($x + 8, $y + self::H - 14);
        $pdf->Cell(self::W - 16, 4, self::text(preg_replace('#^https?://#', '', $card['url']) ?? ''), 0, 0, 'C');
    }

    /** The code as vector squares, quiet zone included, black on white. */
    public static function qr(Document $pdf, float $left, float $top, float $size, string $url): void
    {
        $matrix = QrImages::matrix($url);
        $module = $size / count($matrix);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($left, $top, $size, $size, 'F');
        $pdf->SetFillColor(0, 0, 0);
        foreach ($matrix as $row => $cells) {
            $run = null;
            foreach ([...$cells, false] as $col => $dark) {
                if ($dark && $run === null) {
                    $run = $col;
                } elseif (! $dark && $run !== null) {
                    // A tiny overlap avoids hairline gaps between squares in some viewers.
                    $pdf->Rect($left + $run * $module, $top + $row * $module, ($col - $run) * $module + 0.01, $module + 0.01, 'F');
                    $run = null;
                }
            }
        }
    }

    private static function cutMarks(Document $pdf): void
    {
        $pdf->SetDrawColor(190, 190, 190);
        $pdf->SetLineWidth(0.2);
        for ($i = 0.0; $i < 297; $i += 4) {
            $pdf->Line(self::W, $i, self::W, $i + 2);
        }
        for ($i = 0.0; $i < 210; $i += 4) {
            $pdf->Line($i, self::H, $i + 2, self::H);
        }
    }

    /** Text as typed (UTF-8); Document picks the font, leaves out emoji and draws right-to-left text. */
    public static function text(string $s): string
    {
        return $s;
    }

    /** $s, cut with "…" when it is wider than $width. */
    public static function fit(Document $pdf, string $s, float $width): string
    {
        $pdf->holdFont($s);
        try {
            if ($pdf->GetStringWidth($s) <= $width) {
                return $s;
            }
            while ($s !== '' && $pdf->GetStringWidth($s.'…') > $width) {
                $s = mb_substr($s, 0, -1);
            }

            return rtrim($s).'…';
        } finally {
            $pdf->releaseFont();
        }
    }
}
