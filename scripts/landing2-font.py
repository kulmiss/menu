#!/usr/bin/env python3
"""
Landing 2's Amharic font (owner, 2026-11-22): Noto Sans Ethiopic (SIL Open Font License),
cut down to the Ethiopic letters the Amharic texts use, so the Amharic page loads about
32 KB of font instead of 150 KB. Run it again after the Amharic texts change a lot (letters
it does not have fall back to the phone's own Ethiopic font, which still reads fine).

Needs: pip install fonttools brotli, and the font from npm:
    npm pack @fontsource/noto-sans-ethiopic && tar xzf fontsource-noto-sans-ethiopic-*.tgz
Usage (from the repository root):
    python3 scripts/landing2-font.py path/to/package/files [extra-texts.txt]
"""
import glob
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
OUT = ROOT / 'backend' / 'public' / 'landing' / 'fonts'
ETHIOPIC = [(0x1200, 0x139F), (0x2D80, 0x2DDF), (0xAB00, 0xAB2F)]


def main() -> None:
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    src = pathlib.Path(sys.argv[1])
    text = ''.join(pathlib.Path(f).read_text('utf8') for f in glob.glob(str(ROOT / 'backend' / 'lang' / 'am' / '*.php')))
    if len(sys.argv) > 2:
        text += pathlib.Path(sys.argv[2]).read_text('utf8')
    chars = sorted({c for c in text if any(a <= ord(c) <= b for a, b in ETHIOPIC)})
    chars_file = OUT / 'chars.tmp'
    chars_file.write_text(''.join(chars), 'utf8')
    try:
        for weight in (400, 700):
            subprocess.run([
                'pyftsubset', str(src / f'noto-sans-ethiopic-ethiopic-{weight}-normal.woff2'),
                f'--text-file={chars_file}', '--flavor=woff2', '--layout-features=*', '--no-hinting', '--desubroutinize',
                f'--output-file={OUT / f"noto-sans-ethiopic-{weight}.woff2"}',
            ], check=True)
    finally:
        chars_file.unlink()
    print(f'{len(chars)} Ethiopic letters written to {OUT}')


if __name__ == '__main__':
    main()
