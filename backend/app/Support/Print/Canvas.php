<?php

namespace App\Support\Print;

use FPDF;

/**
 * FPDF with the few drawing extras print materials need: rotation (the top
 * face of a table tent is printed upside down), circles (round stickers),
 * rounded rectangles and dashed lines. All vector, so it stays sharp.
 */
class Canvas extends FPDF
{
    private float $angle = 0.0;

    public function rotate(float $angle, float $x, float $y): void
    {
        if ($this->angle !== 0.0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle !== 0.0) {
            $a = deg2rad($angle);
            $c = cos($a);
            $s = sin($a);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    // FPDF's own hook (snake case kept as FPDF names it).
    protected function _endpage(): void
    {
        if ($this->angle !== 0.0) {
            $this->angle = 0.0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    public function circle(float $x, float $y, float $r, string $style = 'D'): void
    {
        $op = match ($style) {
            'F' => 'f',
            'FD', 'DF' => 'B',
            default => 'S',
        };
        $l = 4 / 3 * (M_SQRT2 - 1) * $r;
        $k = $this->k;
        $h = $this->h;
        $p = fn (float $px, float $py) => sprintf('%.2F %.2F', $px * $k, ($h - $py) * $k);
        $this->_out($p($x + $r, $y).' m');
        $this->_out($p($x + $r, $y - $l).' '.$p($x + $l, $y - $r).' '.$p($x, $y - $r).' c');
        $this->_out($p($x - $l, $y - $r).' '.$p($x - $r, $y - $l).' '.$p($x - $r, $y).' c');
        $this->_out($p($x - $r, $y + $l).' '.$p($x - $l, $y + $r).' '.$p($x, $y + $r).' c');
        $this->_out($p($x + $l, $y + $r).' '.$p($x + $r, $y + $l).' '.$p($x + $r, $y).' c '.$op);
    }

    public function roundedRect(float $x, float $y, float $w, float $h, float $r, string $style = 'F'): void
    {
        $op = match ($style) {
            'F' => 'f',
            'FD', 'DF' => 'B',
            default => 'S',
        };
        $r = min($r, $w / 2, $h / 2);
        $l = 4 / 3 * (M_SQRT2 - 1) * $r;
        $k = $this->k;
        $hp = $this->h;
        $p = fn (float $px, float $py) => sprintf('%.2F %.2F', $px * $k, ($hp - $py) * $k);
        $this->_out($p($x + $r, $y).' m');
        $this->_out($p($x + $w - $r, $y).' l');
        $this->_out($p($x + $w - $r + $l, $y).' '.$p($x + $w, $y + $r - $l).' '.$p($x + $w, $y + $r).' c');
        $this->_out($p($x + $w, $y + $h - $r).' l');
        $this->_out($p($x + $w, $y + $h - $r + $l).' '.$p($x + $w - $r + $l, $y + $h).' '.$p($x + $w - $r, $y + $h).' c');
        $this->_out($p($x + $r, $y + $h).' l');
        $this->_out($p($x + $r - $l, $y + $h).' '.$p($x, $y + $h - $r + $l).' '.$p($x, $y + $h - $r).' c');
        $this->_out($p($x, $y + $r).' l');
        $this->_out($p($x, $y + $r - $l).' '.$p($x + $r - $l, $y).' '.$p($x + $r, $y).' c '.$op);
    }

    /** Dashes for the next lines; dash(0) goes back to solid. */
    public function dash(float $on, float $off = 0.0): void
    {
        $this->_out($on > 0 ? sprintf('[%.3F %.3F] 0 d', $on * $this->k, ($off ?: $on) * $this->k) : '[] 0 d');
    }
}
