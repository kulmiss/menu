<?php

namespace App\Support\PublicMenu;

use FPDF;

/**
 * A printable A4 menu made from the same data as the guest page
 * (MenuPageData::build): logo, name, categories, dishes, prices, sizes,
 * short descriptions and dietary tags. Sold-out dishes are left out; hidden
 * ones are never in the data. FPDF core fonts only (no files to install):
 * text is converted to Windows-1252, which covers the English menu.
 */
class PdfMenu
{
    private const TOP = 15.0;

    private const BOTTOM = 280.0;

    private const LEFT = 15.0;

    private const WIDTH = 180.0;

    private FPDF $pdf;

    /** @var array{0: int, 1: int, 2: int} */
    private array $brand;

    private string $heading;

    private int $columns = 1;

    private int $column = 0;

    private float $y = self::TOP;

    private float $columnTop = self::TOP;

    /**
     * @param  array<string, mixed>  $page  MenuPageData::build() result
     * @param  array{menu_id?: int|null, photos?: bool, columns?: int, compress?: bool}  $options
     */
    public function render(array $page, array $options = []): string
    {
        $theme = $page['theme'];
        $this->brand = self::rgb((string) $theme['theme_color']);
        // Closest PDF fonts to the style: serif headings for A and C, sans for B.
        $this->heading = in_array($theme['style'], ['editorial', 'bistro'], true) || ($theme['font'] ?? '') === 'serif' ? 'Times' : 'Helvetica';
        $this->columns = ($options['columns'] ?? 1) === 2 ? 2 : 1;
        $photos = (bool) ($options['photos'] ?? false);
        $footer = self::text($page['tenant']->name);

        $this->pdf = new class('P', 'mm', 'A4') extends FPDF
        {
            public string $footerText = '';

            public function Footer(): void
            {
                $this->SetY(-12);
                $this->SetFont('Helvetica', '', 7.5);
                $this->SetTextColor(110, 110, 110);
                $this->Cell(0, 5, $this->footerText."   \xB7   ".$this->PageNo().' / {nb}', 0, 0, 'C'); // \xB7 = "·" in Windows-1252
            }
        };
        $this->pdf->footerText = $footer;
        $this->pdf->SetCompression($options['compress'] ?? true); // tests read the text
        $this->pdf->AliasNbPages();
        $this->pdf->SetMargins(self::LEFT, self::TOP, self::LEFT);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetTitle($footer, false);
        $this->pdf->AddPage();

        $this->header($page);

        $menus = array_values(array_filter($page['menus'], fn (array $m) => empty($options['menu_id']) || $m['id'] === $options['menu_id']));
        foreach ($menus as $menu) {
            if (count($menus) > 1) {
                $this->block(9, function (float $x, float $w) use ($menu) {
                    $this->font($this->heading, 'B', 15, $this->brand);
                    $this->pdf->SetXY($x, $this->y);
                    $this->pdf->Cell($w, 8, self::text($menu['name']));
                });
            }
            foreach ($menu['categories'] as $category) {
                $items = array_values(array_filter($category['items'], fn (array $i) => ! $i['sold_out']));
                if ($items === []) {
                    continue;
                }
                // A category title never ends a column on its own: it moves with its first dish.
                $title = 9 + ($category['description'] !== '' ? $this->lines($category['description'], 8.5, $this->columnWidth()) * 4 : 0);
                $this->ensure($title + $this->dishHeight($items[0], $photos));
                $this->block($title, function (float $x, float $w) use ($category) {
                    $this->font($this->heading, 'B', 12.5, $this->brand);
                    $this->pdf->SetXY($x, $this->y + 1);
                    $this->pdf->Cell($w, 6, self::text($category['name']));
                    $this->pdf->SetDrawColor(...$this->brand);
                    $this->pdf->SetLineWidth(0.3);
                    $this->pdf->Line($x, $this->y + 7.5, $x + $w, $this->y + 7.5);
                    if ($category['description'] !== '') {
                        $this->font('Helvetica', 'I', 8.5, [100, 100, 100]);
                        $this->pdf->SetXY($x, $this->y + 8.5);
                        $this->pdf->MultiCell($w, 4, self::text($category['description']));
                    }
                });
                foreach ($items as $item) {
                    $this->dish($item, $photos);
                }
                $this->y += 3;
            }
        }

        return $this->pdf->Output('S');
    }

    /** @param array<string, mixed> $page */
    private function header(array $page): void
    {
        $theme = $page['theme'];
        $logo = $theme['logo']['jpg'] ?? null;
        $x = self::LEFT;
        if ($logo && ($file = self::localFile($logo))) {
            $this->pdf->Image($file, self::LEFT, self::TOP, 20, 20);
            $x += 24;
        }
        $this->font($this->heading, 'B', 22, [25, 23, 20]);
        $this->pdf->SetXY($x, self::TOP + 1);
        $this->pdf->Cell(self::WIDTH - ($x - self::LEFT), 10, self::text($page['tenant']->name));
        $details = array_filter([$page['branch'], $page['info']['address'] ?? null, $page['info']['links'][0]['text'] ?? null]);
        if ($details) {
            $this->font('Helvetica', '', 9, [90, 90, 90]);
            $this->pdf->SetXY($x, self::TOP + 12);
            $this->pdf->Cell(self::WIDTH - ($x - self::LEFT), 5, self::text(implode('   ·   ', $details)));
        }
        $this->y = self::TOP + 25;
        $this->pdf->SetDrawColor(...$this->brand);
        $this->pdf->SetLineWidth(0.6);
        $this->pdf->Line(self::LEFT, $this->y, self::LEFT + self::WIDTH, $this->y);
        $this->y += 5;
        $this->columnTop = $this->y;
    }

    /** @param array<string, mixed> $item */
    private function dish(array $item, bool $photos): void
    {
        $height = $this->dishHeight($item, $photos);
        $this->block($height, function (float $x, float $w) use ($item, $photos) {
            $image = $photos && $item['media'] ? self::localFile((string) ($item['media']['jpg'] ?? '')) : null;
            $top = $this->y;
            if ($image) {
                $this->pdf->Image($image, $x, $top + 0.5, 16, 16);
                $x += 19;
                $w -= 19;
            }
            $price = self::text((string) $item['price']);
            $this->font('Helvetica', 'B', 10, [235, 235, 235]);
            $priceWidth = $price === '' ? 0 : $this->pdf->GetStringWidth($price) + 2;
            $this->font('Helvetica', 'B', 10.5, [25, 23, 20]);
            $this->pdf->SetXY($x, $top);
            $this->pdf->MultiCell($w - $priceWidth, 5, self::text($item['name']));
            $below = $this->pdf->GetY();
            if ($price !== '') {
                $this->font('Helvetica', 'B', 10, $this->brand);
                $this->pdf->SetXY($x + $w - $priceWidth, $top);
                $this->pdf->Cell($priceWidth, 5, $price, 0, 0, 'R');
            }
            $this->pdf->SetXY($x, $below);
            foreach ($this->details($item) as [$text, $style, $color]) {
                $this->font('Helvetica', $style, 8.5, $color);
                $this->pdf->SetX($x);
                $this->pdf->MultiCell($w, 4, self::text($text));
            }
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{0: string, 1: string, 2: array{0: int, 1: int, 2: int}}>
     */
    private function details(array $item): array
    {
        $rows = [];
        $sizes = $item['sheet']['v'] ?? [];
        if ($sizes !== []) {
            $rows[] = [implode('   ·   ', array_map(fn (array $s) => trim($s[0].' '.$s[1]), $sizes)), '', [70, 70, 70]];
        }
        if ($item['summary'] !== '') {
            $rows[] = [$item['summary'], '', [95, 95, 95]];
        }
        $tags = array_merge(
            $item['new'] ? [__('public.new')] : [],
            $item['popular'] ? [__('public.popular')] : [],
            array_map(fn (array $t) => $t['label'], array_filter($item['tags'], fn (array $t) => ! in_array($t['key'], ['new', 'popular'], true))),
        );
        if ($tags !== []) {
            $rows[] = [implode(' · ', $tags), 'I', $this->brand];
        }

        return $rows;
    }

    /** @param array<string, mixed> $item */
    private function dishHeight(array $item, bool $photos): float
    {
        $w = $this->columnWidth() - ($photos && $item['media'] ? 19 : 0);
        $this->font('Helvetica', 'B', 10, [0, 0, 0]);
        $price = $item['price'] === '' ? 0 : $this->pdf->GetStringWidth(self::text((string) $item['price'])) + 2;
        $height = $this->lines((string) $item['name'], 10.5, $w - $price, 'B') * 5;
        foreach ($this->details($item) as [$text, $style]) {
            $height += $this->lines($text, 8.5, $w, $style) * 4;
        }

        return max($height, $photos && $item['media'] ? 17.5 : 0) + 3.5;
    }

    /** Draws something of a known height in the current column, moving on when it doesn't fit. */
    private function block(float $height, callable $draw): void
    {
        $this->ensure($height);
        $draw(self::LEFT + $this->column * ($this->columnWidth() + 8), $this->columnWidth());
        $this->y += $height;
    }

    private function ensure(float $height): void
    {
        if ($this->y + $height <= self::BOTTOM) {
            return;
        }
        if ($this->column < $this->columns - 1) {
            $this->column++;
        } else {
            $this->pdf->AddPage();
            $this->column = 0;
            $this->columnTop = self::TOP;
        }
        $this->y = $this->columnTop;
    }

    private function columnWidth(): float
    {
        return $this->columns === 2 ? (self::WIDTH - 8) / 2 : self::WIDTH;
    }

    /** How many lines MultiCell will use for this text. */
    private function lines(string $text, float $size, float $width, string $style = ''): int
    {
        $this->pdf->SetFont('Helvetica', $style, $size);
        $count = 0;
        foreach (explode("\n", self::text($text)) as $paragraph) {
            $line = '';
            $count++;
            foreach (explode(' ', $paragraph) as $word) {
                $try = $line === '' ? $word : $line.' '.$word;
                if ($line !== '' && $this->pdf->GetStringWidth($try) > $width - 2) {
                    $count++;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
        }

        return $count;
    }

    /** @param array{0: int, 1: int, 2: int} $color */
    private function font(string $family, string $style, float $size, array $color): void
    {
        $this->pdf->SetFont($family, $style, $size);
        $this->pdf->SetTextColor(...$color);
    }

    /** FPDF's core fonts use Windows-1252. */
    private static function text(string $value): string
    {
        $value = str_replace(["\u{2013}", "\u{2014}", "\u{2019}", "\u{2026}"], ['-', '-', "'", '...'], $value);

        return (string) (@iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value) ?: '');
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /** A photo URL of ours → its JPEG file on disk (FPDF can't read WebP). */
    private static function localFile(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $file = is_string($path) ? public_path(ltrim($path, '/')) : '';

        return $file !== '' && is_file($file) && str_ends_with(strtolower($file), '.jpg') ? $file : null;
    }
}
