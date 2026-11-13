<?php

namespace App\Support\Insights;

use App\Models\Tenant;
use App\Models\TenantAppearance;
use App\Support\PlatformSettings;
use App\Support\Print\PrintMaterials;
use App\Support\PublicMenu\PageTheme;
use App\Support\Qr\TableCards;
use Carbon\CarbonImmutable;
use FPDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The Insights report as a one- or two-page A4 PDF (owner, 2026-11-14,
 * QUESTIONS #180): summary numbers with the change against the previous
 * period, a chart of views and scans per day, top dishes and top QR codes.
 * Vector only (FPDF core fonts), uncompressed so it can be searched and tested.
 */
class ReportPdf
{
    private const LEFT = 14.0;

    private const WIDTH = 182.0;

    private FPDF $pdf;

    private float $y = 0;

    /** @var list<int> */
    private array $brand;

    /**
     * @param  array<string, mixed>  $report  InsightsReport::build()
     * @param  array{name: string, colour: string, logo: string|null, made: string}  $o
     */
    public function __construct(private array $report, private array $o)
    {
        $this->pdf = new FPDF('P', 'mm', 'A4');
        $this->pdf->SetCompression(false);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetMargins(self::LEFT, 14, self::LEFT);
        $this->pdf->SetCreator('Menu');
        $this->pdf->SetTitle(TableCards::text(__('insights.export.title').' - '.$o['name']));
        $this->brand = PrintMaterials::rgb($o['colour']);
    }

    /**
     * The PDF for a restaurant, with its logo and colour; runs inside its tenant context.
     *
     * @param  array<string, mixed>  $report
     */
    public static function forTenant(Tenant $tenant, array $report): string
    {
        $appearance = TenantAppearance::current();
        $logo = $appearance->logo;
        $file = $logo !== null && isset($logo->variants['jpg']) ? Storage::disk($logo->disk)->path($logo->base_path.'/'.$logo->variants['jpg']) : null;

        return (new self($report, [
            'name' => $tenant->name,
            'colour' => PageTheme::from($appearance)['theme_color'],
            'logo' => $file !== null && is_file($file) ? $file : null,
            'made' => __('insights.export.made', ['date' => now($tenant->timezone)->format('j M Y, H:i'), 'app' => PlatformSettings::get('name')]),
        ]))->render();
    }

    /** @param array<string, mixed> $report */
    public static function filename(Tenant $tenant, array $report): string
    {
        return Str::slug($tenant->name.' insights '.$report['from'].' '.$report['to']).'.pdf';
    }

    public function render(): string
    {
        $this->pdf->AddPage();
        $this->header();
        $this->summary();
        $this->chart();
        $this->dishes();
        $this->codes();
        $this->footer();

        return $this->pdf->Output('S');
    }

    private function header(): void
    {
        $this->pdf->SetFillColor(...$this->brand);
        $this->pdf->Rect(0, 0, 210, 3, 'F');
        $x = self::LEFT;
        if ($this->o['logo'] !== null && is_file($this->o['logo'])) {
            $this->pdf->Image($this->o['logo'], self::LEFT, 12, 16, 16);
            $x += 20;
        }
        $this->font('B', 18);
        $this->pdf->SetXY($x, 12);
        $this->pdf->Cell(self::WIDTH - ($x - self::LEFT), 8, TableCards::fit($this->pdf, $this->o['name'], self::WIDTH - ($x - self::LEFT)));
        $this->font('', 10, [95, 90, 85]);
        $this->pdf->SetXY($x, 20.5);
        $period = __('insights.export.period', [
            'from' => CarbonImmutable::parse($this->report['from'])->format('j M Y'),
            'to' => CarbonImmutable::parse($this->report['to'])->format('j M Y'),
        ]);
        $where = $this->report['branch'] ?? __('insights.export.all_branches');
        $this->pdf->Cell(self::WIDTH, 5, TableCards::text(__('insights.export.title').'  ·  '.$period.'  ·  '.$where));
        $this->y = 34;
    }

    private function summary(): void
    {
        $tiles = [
            'views' => __('insights.export.views'),
            'scans' => __('insights.export.scans'),
            'item_views' => __('insights.export.item_views'),
            'order_adds' => __('insights.export.order_adds'),
        ];
        $w = (self::WIDTH - 3 * 4) / 4;
        $i = 0;
        foreach ($tiles as $key => $label) {
            $x = self::LEFT + $i++ * ($w + 4);
            $this->pdf->SetFillColor(244, 242, 239);
            $this->pdf->Rect($x, $this->y, $w, 25, 'F');
            $this->font('B', 18);
            $this->pdf->SetXY($x + 3, $this->y + 3);
            $this->pdf->Cell($w - 6, 8, number_format($this->report['totals'][$key]));
            $this->font('', 8.5, [80, 76, 72]);
            $this->pdf->SetXY($x + 3, $this->y + 11.5);
            $this->pdf->Cell($w - 6, 4, TableCards::fit($this->pdf, $label, $w - 6));
            $change = InsightsReport::change($this->report['totals'][$key], $this->report['previous'][$key]);
            [$text, $colour] = match (true) {
                $change === null => [__('insights.export.no_previous'), [110, 106, 102]],
                $change > 0 => [__('insights.export.up', ['pct' => $change]), [21, 110, 56]],
                $change < 0 => [__('insights.export.down', ['pct' => abs($change)]), [176, 36, 24]],
                default => [__('insights.export.same'), [110, 106, 102]],
            };
            $this->font('B', 8, $colour);
            $this->pdf->SetXY($x + 3, $this->y + 17.5);
            $this->pdf->Cell($w - 6, 4, TableCards::fit($this->pdf, $text, $w - 6));
        }
        $this->y += 33;
    }

    /** Views (brand colour) and scans (dark grey) per day as paired bars, with three grid lines. */
    private function chart(): void
    {
        $this->title(__('insights.export.chart'));
        $days = $this->report['days'];
        $h = 52.0;
        $left = self::LEFT + 10;
        $width = self::WIDTH - 10;
        $top = $this->y;
        $max = max(1, ...array_map(fn ($d) => max($d['views'], $d['scans']), $days));
        $step = self::niceStep($max);
        $ceiling = $step * 4;
        $this->pdf->SetDrawColor(225, 222, 218);
        $this->pdf->SetLineWidth(0.2);
        for ($g = 0; $g <= 4; $g++) {
            $gy = $top + $h - $h * $g / 4;
            $this->pdf->Line($left, $gy, $left + $width, $gy);
            $this->font('', 7, [110, 106, 102]);
            $this->pdf->SetXY(self::LEFT, $gy - 2);
            $this->pdf->Cell(9, 4, number_format($step * $g), 0, 0, 'R');
        }
        $slot = $width / max(1, count($days));
        $bar = max(0.3, $slot * 0.38);
        foreach ($days as $i => $d) {
            $x = $left + $i * $slot + ($slot - 2 * $bar) / 2;
            foreach ([[$d['views'], $this->brand], [$d['scans'], [70, 66, 62]]] as $j => [$value, $colour]) {
                if ($value > 0) {
                    $bh = $h * $value / $ceiling;
                    $this->pdf->SetFillColor(...$colour);
                    $this->pdf->Rect($x + $j * $bar, $top + $h - $bh, $bar, $bh, 'F');
                }
            }
        }
        $this->font('', 7, [110, 106, 102]);
        $labels = [0 => 'L', intdiv(count($days) - 1, 2) => 'C', count($days) - 1 => 'R'];
        foreach ($labels as $i => $align) {
            $date = CarbonImmutable::parse($days[$i]['date'])->format('j M');
            $cx = $left + $i * $slot + $slot / 2;
            $this->pdf->SetXY(match ($align) {
                'L' => $left,
                'R' => $left + $width - 30,
                default => $cx - 15,
            }, $top + $h + 1);
            $this->pdf->Cell(30, 4, $date, 0, 0, $align);
        }
        // Legend.
        $ly = $top + $h + 6.5;
        foreach ([[__('insights.export.views'), $this->brand], [__('insights.export.scans'), [70, 66, 62]]] as $k => [$label, $colour]) {
            $lx = $left + $k * 40;
            $this->pdf->SetFillColor(...$colour);
            $this->pdf->Rect($lx, $ly + 0.8, 3, 3, 'F');
            $this->font('', 8, [60, 56, 52]);
            $this->pdf->SetXY($lx + 4.5, $ly);
            $this->pdf->Cell(34, 4.5, TableCards::text($label));
        }
        $this->y = $ly + 11;
    }

    private function dishes(): void
    {
        $rows = array_slice($this->report['dishes'], 0, 10);
        $this->table(
            __('insights.export.top_dishes'),
            [['#', 8, 'R'], [__('insights.export.dish'), 82, 'L'], [__('insights.export.category'), 44, 'L'], [__('insights.export.item_views'), 24, 'R'], [__('insights.export.order_adds'), 24, 'R']],
            array_map(fn ($r, $i) => [(string) ($i + 1), $r['name'], $r['category'], number_format($r['views']), number_format($r['adds'])], $rows, array_keys($rows)),
        );
    }

    private function codes(): void
    {
        $rows = array_slice($this->report['codes'], 0, 10);
        $total = max(1, $this->report['totals']['scans']);
        $this->table(
            __('insights.export.top_codes'),
            [['#', 8, 'R'], [__('insights.export.code'), 82, 'L'], [__('insights.export.branch'), 44, 'L'], [__('insights.export.scans'), 24, 'R'], [__('insights.export.share'), 24, 'R']],
            array_map(fn ($r, $i) => [(string) ($i + 1), $r['label'], $r['branch'], number_format($r['scans']), round($r['scans'] / $total * 100).' %'], $rows, array_keys($rows)),
        );
    }

    /**
     * @param  list<array{0: string, 1: float|int, 2: string}>  $columns
     * @param  list<list<string>>  $rows
     */
    private function table(string $title, array $columns, array $rows): void
    {
        $need = 12 + 6 * max(1, count($rows));
        if ($this->y + $need > 280) {
            $this->footer();
            $this->pdf->AddPage();
            $this->y = 16;
        }
        $this->title($title);
        $this->font('B', 8, [95, 90, 85]);
        $x = self::LEFT;
        foreach ($columns as [$label, $w, $align]) {
            $this->pdf->SetXY($x, $this->y);
            $this->pdf->Cell($w, 5, TableCards::fit($this->pdf, $label, $w - 1), 0, 0, $align);
            $x += $w;
        }
        $this->y += 5.5;
        $this->pdf->SetDrawColor(225, 222, 218);
        $this->pdf->SetLineWidth(0.2);
        if ($rows === []) {
            $this->font('', 9, [110, 106, 102]);
            $this->pdf->SetXY(self::LEFT, $this->y);
            $this->pdf->Cell(self::WIDTH, 6, TableCards::text(__('insights.export.nothing')));
            $this->y += 10;

            return;
        }
        foreach ($rows as $row) {
            $this->pdf->Line(self::LEFT, $this->y, self::LEFT + self::WIDTH, $this->y);
            $x = self::LEFT;
            foreach ($columns as $c => [, $w, $align]) {
                $this->font($c === 1 ? 'B' : '', 9, $c === 0 ? [110, 106, 102] : [25, 23, 20]);
                $this->pdf->SetXY($x, $this->y + 0.5);
                $this->pdf->Cell($w, 5.5, TableCards::fit($this->pdf, $row[$c], $w - 1.5), 0, 0, $align);
                $x += $w;
            }
            $this->y += 6;
        }
        $this->y += 6;
    }

    private function title(string $text): void
    {
        $this->font('B', 11.5);
        $this->pdf->SetXY(self::LEFT, $this->y);
        $this->pdf->Cell(self::WIDTH, 6, TableCards::text($text));
        $this->y += 8;
    }

    private function footer(): void
    {
        $this->font('', 7.5, [120, 116, 112]);
        $this->pdf->SetXY(self::LEFT, 286);
        $this->pdf->Cell(self::WIDTH / 2, 4, TableCards::text($this->o['made']));
        $this->pdf->SetXY(self::LEFT + self::WIDTH / 2, 286);
        $this->pdf->Cell(self::WIDTH / 2, 4, TableCards::text(__('insights.export.page', ['page' => $this->pdf->PageNo()])), 0, 0, 'R');
    }

    /** @param list<int> $colour */
    private function font(string $style, float $size, array $colour = [25, 23, 20]): void
    {
        $this->pdf->SetFont('Helvetica', $style, $size);
        $this->pdf->SetTextColor(...$colour);
    }

    /** A round step so four grid lines cover the highest bar (1, 2, 5 × 10ⁿ). */
    public static function niceStep(int $max): int
    {
        $raw = max(1, (int) ceil($max / 4));
        $mag = 10 ** (int) floor(log10($raw));
        foreach ([1, 2, 5, 10] as $m) {
            if ($m * $mag >= $raw) {
                return $m * $mag;
            }
        }

        return 10 * $mag;
    }
}
