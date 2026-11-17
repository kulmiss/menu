<?php

namespace App\Support\Pdf;

use tFPDF;
use TTFontFile;

/**
 * The PDF writer for every PDF the app makes (menu, table cards, print
 * materials, invoices, Insights report). Text goes in as UTF-8, as typed.
 *
 * Text that Windows-1252 can hold (English and Western European menus) uses
 * the built-in Helvetica / Times exactly as before, so those PDFs don't
 * change. Any other text (Amharic, Arabic, Greek, Cyrillic, Hebrew, …) uses
 * the bundled MenuPdfSans font (resources/fonts/pdf, only the letters used
 * are embedded). Emoji and anything the fonts can't draw are left out
 * cleanly. See QUESTIONS #205.
 */
class Document extends tFPDF
{
    public const UNICODE = 'menupdfsans';

    /** @var array<string, array{name: string, desc: array<string, mixed>, up: int, ut: int, cw: string, size: int}> */
    private static array $metrics = [];

    private string $family = 'helvetica';

    private string $style = '';

    /** @var list<bool> font choices held by holdFont() (innermost last); empty: chosen per text */
    private array $held = [];

    /** Inside FPDF's own text methods: the text is ready, don't touch it again. */
    private bool $raw = false;

    private bool $unicodeAdded = false;

    /** @var array<string, string> */
    private array $cleaned = [];

    /**
     * @param  string  $family
     * @param  string  $style
     * @param  float|int  $size
     */
    public function SetFont($family, $style = '', $size = 0): void
    {
        $this->family = strtolower((string) $family);
        $this->style = strtoupper((string) $style);
        parent::SetFont($family, $style, $size);
    }

    /**
     * Keeps one font choice (the one $text needs) for the calls that follow,
     * until the matching releaseFont(): a text measured word by word is then
     * measured in the font it will be drawn in. Holds can nest.
     */
    public function holdFont(string $text): void
    {
        $this->held[] = $this->held === [] ? $this->needsUnicode($text) : end($this->held);
    }

    public function releaseFont(): void
    {
        array_pop($this->held);
    }

    /**
     * @param  string  $title
     * @param  bool  $isUTF8  always UTF-8 here
     */
    public function SetTitle($title, $isUTF8 = false): void
    {
        parent::SetTitle($this->clean((string) $title), true);
    }

    /** @param string $s */
    public function GetStringWidth($s): float
    {
        if ($this->raw) {
            return (float) parent::GetStringWidth($s);
        }

        return (float) $this->with((string) $s, fn (string $t) => parent::GetStringWidth($t));
    }

    /**
     * @param  float|int  $w
     * @param  float|int  $h
     * @param  string  $txt
     * @param  int|string  $border
     * @param  int  $ln
     * @param  string  $align
     * @param  bool  $fill
     * @param  int|string  $link
     */
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = ''): void
    {
        if ($this->raw) {
            parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);

            return;
        }
        $this->with((string) $txt, fn (string $t, bool $unicode) => $this->inside(fn () => parent::Cell($w, $h, $unicode ? Script::visual($t) : $t, $border, $ln, $align, $fill, $link)));
    }

    /**
     * @param  float|int  $x
     * @param  float|int  $y
     * @param  string  $txt
     */
    public function Text($x, $y, $txt): void
    {
        $this->with((string) $txt, fn (string $t, bool $unicode) => $this->inside(fn () => parent::Text($x, $y, $unicode ? Script::visual($t) : $t)));
    }

    /**
     * @param  float|int  $h
     * @param  string  $txt
     * @param  int|string  $link
     */
    public function Write($h, $txt, $link = ''): void
    {
        $this->with((string) $txt, fn (string $t, bool $unicode) => $this->inside(fn () => parent::Write($h, $unicode ? Script::visual($t) : $t, $link)));
    }

    /**
     * @param  float|int  $w
     * @param  float|int  $h
     * @param  string  $txt
     * @param  int|string  $border
     * @param  string  $align
     * @param  bool  $fill
     */
    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false): void
    {
        $this->with((string) $txt, function (string $t, bool $unicode) use ($w, $h, $border, $align, $fill) {
            if ($unicode && Script::hasRtl($t)) {
                $this->rtlMultiCell($w, $h, $t, $border, $align, $fill);

                return;
            }
            $this->inside(fn () => parent::MultiCell($w, $h, $t, $border, $align, $fill));
        });
    }

    /**
     * Runs one of FPDF's own text methods: the text is ready, and the calls it
     * makes back into this class (GetStringWidth for centring, Cell for each
     * line) must not prepare it again or switch the font.
     */
    private function inside(callable $call): void
    {
        $outer = $this->raw;
        $this->raw = true;
        try {
            $call();
        } finally {
            $this->raw = $outer;
        }
    }

    /**
     * Runs $draw with the text cleaned and in the font it needs: Windows-1252
     * bytes for the built-in fonts, or UTF-8 (Arabic joined) for MenuPdfSans.
     *
     * @template T
     *
     * @param  callable(string, bool): T  $draw
     * @return T
     */
    private function with(string $text, callable $draw): mixed
    {
        $unicode = $this->held === [] ? $this->needsUnicode($text) : end($this->held);
        $clean = $this->clean($text);
        if (! $unicode) {
            return $draw($this->cp1252($clean), false);
        }
        $size = $this->FontSizePt;
        $this->useUnicode();
        parent::SetFont(self::UNICODE, str_contains($this->style, 'B') ? 'B' : '', $size);
        try {
            return $draw(Script::shape($clean), true);
        } finally {
            parent::SetFont($this->family, $this->style, $size);
        }
    }

    private function needsUnicode(string $text): bool
    {
        $clean = $this->clean($text);

        return $clean !== '' && @iconv('UTF-8', 'windows-1252', $clean) === false;
    }

    private function cp1252(string $text): string
    {
        return (string) @iconv('UTF-8', 'windows-1252//IGNORE', $text);
    }

    private function clean(string $text): string
    {
        // The same names and words are measured and drawn many times: clean each once.
        if (isset($this->cleaned[$text])) {
            return $this->cleaned[$text];
        }
        if (count($this->cleaned) > 20000) {
            $this->cleaned = [];
        }
        $cw = self::metrics('MenuPdfSans-Regular.ttf')['cw'];
        // Printable ASCII is always drawable; the rest when the bundled font has it.
        $drawable = fn (int $cp) => $cp < 0x7F || Script::shapes($cp) || (2 * $cp + 1 < strlen($cw) && (ord($cw[2 * $cp]) << 8 | ord($cw[2 * $cp + 1])) > 0);

        return $this->cleaned[$text] = Script::clean($text, $drawable);
    }

    /** Right-to-left paragraphs: wrapped in reading order, each line then drawn right to left. */
    private function rtlMultiCell(float $w, float $h, string $text, mixed $border, string $align, bool $fill): void
    {
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $max = $w - 2 * $this->cMargin;
        $x = $this->x;
        $this->inside(function () use ($w, $h, $text, $border, $align, $fill, $max, $x) {
            foreach (explode("\n", $text) as $paragraph) {
                $line = '';
                foreach (explode(' ', $paragraph) as $word) {
                    $try = $line === '' ? $word : $line.' '.$word;
                    if ($line !== '' && parent::GetStringWidth($try) > $max) {
                        $this->SetX($x);
                        parent::Cell($w, $h, Script::visual($line), $border, 2, $align === 'J' ? 'L' : $align, $fill);
                        $line = $word;
                    } else {
                        $line = $try;
                    }
                }
                $this->SetX($x);
                parent::Cell($w, $h, Script::visual($line), $border, 2, $align === 'J' ? 'L' : $align, $fill);
            }
        });
        $this->x = $this->lMargin;
    }

    /** Registers MenuPdfSans (regular and bold) once per PDF; the font files are read once per process. */
    private function useUnicode(): void
    {
        if ($this->unicodeAdded) {
            return;
        }
        $this->unicodeAdded = true;
        foreach (['' => 'MenuPdfSans-Regular.ttf', 'B' => 'MenuPdfSans-Bold.ttf'] as $style => $file) {
            $path = resource_path('fonts/pdf/'.$file);
            $m = self::metrics($file);
            $key = self::UNICODE.$style;
            $this->fonts[$key] = [
                'i' => count($this->fonts) + 1, 'type' => 'TTF', 'name' => $m['name'], 'desc' => $m['desc'], 'up' => $m['up'], 'ut' => $m['ut'],
                'cw' => $m['cw'], 'ttffile' => $path, 'fontkey' => $key, 'subset' => range(0, empty($this->AliasNbPages) ? 32 : 57),
                // tFPDF keeps a small width cache next to this name (storage, never the code folders).
                'unifilename' => self::cache().'/'.strtolower(pathinfo($file, PATHINFO_FILENAME)),
            ];
            $this->FontFiles[$key] = ['length1' => $m['size'], 'type' => 'TTF', 'ttffile' => $path];
            $this->FontFiles[$file] = ['type' => 'TTF'];
        }
    }

    /**
     * The font's widths and description, read once per process.
     *
     * @return array{name: string, desc: array<string, mixed>, up: int, ut: int, cw: string, size: int}
     */
    private static function metrics(string $file): array
    {
        if (! isset(self::$metrics[$file])) {
            $path = resource_path('fonts/pdf/'.$file);
            $ttf = new TTFontFile;
            $ttf->getMetrics($path);
            self::$metrics[$file] = [
                'name' => (string) preg_replace('/[ ()]/', '', $ttf->fullName),
                'desc' => [
                    'Ascent' => (int) round($ttf->ascent), 'Descent' => (int) round($ttf->descent), 'CapHeight' => (int) round($ttf->capHeight),
                    'Flags' => $ttf->flags, 'FontBBox' => '['.implode(' ', array_map(fn ($v) => (string) round($v), $ttf->bbox)).']',
                    'ItalicAngle' => $ttf->italicAngle, 'StemV' => (int) round($ttf->stemV), 'MissingWidth' => (int) round($ttf->defaultWidth),
                ],
                'up' => (int) round($ttf->underlinePosition), 'ut' => (int) round($ttf->underlineThickness),
                'cw' => $ttf->charWidths, 'size' => (int) filesize($path),
            ];
        }

        return self::$metrics[$file];
    }

    private static function cache(): string
    {
        $dir = storage_path('framework/cache/pdf-fonts');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
