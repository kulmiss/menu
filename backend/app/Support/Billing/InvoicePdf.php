<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use App\Support\PublicMenu\MenuPageData;
use FPDF;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Invoice as an A4 PDF (FPDF, core fonts). Everything printed comes from the
 * invoice row itself, so a re-download looks exactly like the original.
 */
class InvoicePdf
{
    private const INK = [17, 24, 39];

    private const MUTED = [90, 99, 115];

    private const LINE = [226, 230, 236];

    public static function render(Invoice $invoice): string
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(18, 18, 18);
        // Uncompressed: a one-page text invoice stays small, and its text can be searched and checked.
        $pdf->SetCompression(false);
        $pdf->SetAutoPageBreak(true, 22);
        $pdf->SetCreator((string) ($invoice->seller['name'] ?? ''));
        $pdf->SetTitle(self::t(__('invoice.title').' '.$invoice->number));
        $pdf->AddPage();
        $seller = $invoice->seller;
        $customer = $invoice->customer;

        // Header: logo or name on the left, title and number on the right.
        $logo = self::logo($seller['logo'] ?? null);
        if ($logo !== null) {
            [$w, $h] = getimagesize($logo) ?: [1, 1];
            $height = min(16.0, 40.0 * $h / max(1, $w));
            $pdf->Image($logo, 18, 18, $height * $w / max(1, $h), $height, 'PNG');
            @unlink($logo);
        } else {
            $pdf->SetFont('Helvetica', 'B', 16);
            self::ink($pdf);
            $pdf->SetXY(18, 20);
            $pdf->Cell(100, 8, self::t((string) $seller['name']));
        }
        $pdf->SetFont('Helvetica', 'B', 22);
        self::ink($pdf);
        $pdf->SetXY(110, 18);
        $pdf->Cell(82, 10, self::t(__('invoice.title')), 0, 2, 'R');
        $pdf->SetFont('Helvetica', '', 10);
        self::muted($pdf);
        $pdf->Cell(82, 5, self::t(__('invoice.number').': '.$invoice->number), 0, 2, 'R');
        $pdf->Cell(82, 5, self::t(__('invoice.date').': '.self::date($invoice->issued_at)), 0, 2, 'R');
        $void = $invoice->status === Invoice::VOID;
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(...($void ? [185, 28, 28] : [21, 128, 61]));
        $pdf->SetTextColor(255, 255, 255);
        $label = self::t(__('invoice.status.'.$invoice->status));
        $w = $pdf->GetStringWidth($label) + 8;
        $pdf->SetXY(192 - $w, $pdf->GetY() + 2);
        $pdf->Cell($w, 7, $label, 0, 0, 'C', true);

        // From / Bill to
        $top = 52.0;
        self::block($pdf, 18, $top, __('invoice.from'), array_filter([
            $seller['name'] ?? '', $seller['address'] ?? '',
            ($seller['tax_id'] ?? '') !== '' ? __('invoice.tax_id', ['id' => $seller['tax_id']]) : '', $seller['email'] ?? '',
        ]));
        self::block($pdf, 110, $top, __('invoice.bill_to'), array_filter([
            $customer['name'] ?? '', $customer['address'] ?? '',
            ($customer['owner'] ?? '') !== '' ? __('invoice.owner', ['name' => $customer['owner']]) : '', $customer['email'] ?? '',
            ($customer['slug'] ?? '') !== '' ? __('invoice.menu_address', ['url' => preg_replace('#^https?://#', '', url('/'.$customer['slug']))]) : '',
        ]));

        // Line table
        $y = max($pdf->GetY(), $top + 30) + 10;
        $pdf->SetDrawColor(...self::LINE);
        $pdf->SetFillColor(246, 247, 249);
        $pdf->SetXY(18, $y);
        $pdf->SetFont('Helvetica', 'B', 9);
        self::muted($pdf);
        $pdf->Cell(92, 9, self::t(__('invoice.description')), 'B', 0, 'L', true);
        $pdf->Cell(52, 9, self::t(__('invoice.period')), 'B', 0, 'L', true);
        $pdf->Cell(30, 9, self::t(__('invoice.amount')), 'B', 1, 'R', true);
        $pdf->SetFont('Helvetica', '', 10);
        self::ink($pdf);
        $line = $invoice->plan_name ? __('invoice.line', ['plan' => $invoice->plan_name]) : __('invoice.line_no_plan');
        $pdf->SetX(18);
        $pdf->Cell(92, 11, self::t($line), 'B');
        $pdf->Cell(52, 11, self::t(self::date($invoice->period_start).' – '.self::date($invoice->period_end)), 'B');
        $pdf->Cell(30, 11, self::t(self::money($invoice->total_minor, $invoice->currency)), 'B', 1, 'R');

        // Totals
        $pdf->Ln(3);
        $rows = [[__('invoice.subtotal'), self::money($invoice->subtotal_minor, $invoice->currency), false]];
        if ((float) $invoice->tax_rate > 0) {
            $rows[] = [__('invoice.tax', ['rate' => rtrim(rtrim(number_format((float) $invoice->tax_rate, 2), '0'), '.')]), self::money($invoice->tax_minor, $invoice->currency), false];
        }
        $rows[] = [__('invoice.total'), self::money($invoice->total_minor, $invoice->currency), true];
        foreach ($rows as [$label, $value, $bold]) {
            $pdf->SetX(110);
            $pdf->SetFont('Helvetica', $bold ? 'B' : '', $bold ? 12 : 10);
            $bold ? self::ink($pdf) : self::muted($pdf);
            $pdf->Cell(52, 8, self::t($label), $bold ? 'T' : 0);
            self::ink($pdf);
            $pdf->Cell(30, 8, self::t($value), $bold ? 'T' : 0, 1, 'R');
        }

        // Payment, note, history
        $pdf->Ln(6);
        self::heading($pdf, __('invoice.payment'));
        $pdf->SetFont('Helvetica', '', 10);
        self::ink($pdf);
        $method = __('invoice.methods.'.$invoice->method);
        $pdf->MultiCell(174, 5.5, self::t(__('invoice.paid_on', ['date' => self::date($invoice->paid_at), 'method' => is_string($method) ? $method : $invoice->method])));
        if ($invoice->reference) {
            $pdf->MultiCell(174, 5.5, self::t(__('invoice.reference', ['ref' => $invoice->reference])));
        }
        if ($invoice->note) {
            $pdf->Ln(3);
            self::heading($pdf, __('invoice.note'));
            $pdf->SetFont('Helvetica', '', 10);
            self::ink($pdf);
            $pdf->MultiCell(174, 5.5, self::t($invoice->note));
        }
        if ($invoice->replaces_id) {
            $pdf->Ln(3);
            $pdf->SetFont('Helvetica', 'I', 9);
            self::muted($pdf);
            $pdf->MultiCell(174, 5, self::t(__('invoice.replaces', ['number' => (string) $invoice->replaces?->number])));
        }
        if ($void) {
            $pdf->Ln(3);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(185, 28, 28);
            $pdf->MultiCell(174, 5.5, self::t(__('invoice.voided', ['date' => self::date($invoice->voided_at ?? now()), 'reason' => (string) $invoice->void_reason])));
        }

        // Footer text at the bottom of the page
        if (($seller['footer'] ?? '') !== '') {
            $pdf->SetAutoPageBreak(false);
            $pdf->SetXY(18, 270);
            $pdf->SetDrawColor(...self::LINE);
            $pdf->Line(18, 268, 192, 268);
            $pdf->SetFont('Helvetica', '', 8.5);
            self::muted($pdf);
            $pdf->MultiCell(174, 4.5, self::t((string) $seller['footer']), 0, 'C');
        }

        return $pdf->Output('S');
    }

    public static function filename(Invoice $invoice): string
    {
        return $invoice->number.'.pdf';
    }

    public static function money(int $minor, string $currency): string
    {
        return $currency.' '.MenuPageData::number($minor);
    }

    /** @param  array<int, string>  $lines */
    private static function block(FPDF $pdf, float $x, float $y, string $title, array $lines): void
    {
        $pdf->SetXY($x, $y);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        self::muted($pdf);
        $pdf->Cell(82, 5, self::t(mb_strtoupper($title)), 0, 2);
        foreach (array_values($lines) as $i => $line) {
            $pdf->SetFont('Helvetica', $i === 0 ? 'B' : '', $i === 0 ? 11 : 9.5);
            self::ink($pdf);
            $pdf->SetX($x);
            $pdf->MultiCell(82, $i === 0 ? 6 : 5, self::t($line));
        }
    }

    private static function heading(FPDF $pdf, string $text): void
    {
        $pdf->SetFont('Helvetica', 'B', 8.5);
        self::muted($pdf);
        $pdf->Cell(174, 6, self::t(mb_strtoupper($text)), 0, 1);
    }

    /** The logo as a temporary PNG without transparency (FPDF can't read alpha). */
    private static function logo(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('public')->exists($path) || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $src = @imagecreatefromstring((string) Storage::disk('public')->get($path));
        if ($src === false) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $out = imagecreatetruecolor($w, $h);
        imagefill($out, 0, 0, (int) imagecolorallocate($out, 255, 255, 255));
        imagecopy($out, $src, 0, 0, 0, 0, $w, $h);
        $file = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($out, $file);

        return $file;
    }

    private static function date(?Carbon $date): string
    {
        return $date ? $date->format('j M Y') : '';
    }

    private static function ink(FPDF $pdf): void
    {
        $pdf->SetTextColor(...self::INK);
    }

    private static function muted(FPDF $pdf): void
    {
        $pdf->SetTextColor(...self::MUTED);
    }

    private static function t(string $s): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);

        return $converted === false ? '' : $converted;
    }
}
