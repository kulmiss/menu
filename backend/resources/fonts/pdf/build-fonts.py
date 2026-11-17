"""Builds the PDF fonts (MenuPdfSans-Regular.ttf, MenuPdfSans-Bold.ttf).

One TrueType font per weight that covers what restaurants type, for the
printable PDFs only (the website never loads it):
  Latin (incl. Vietnamese), Greek, Cyrillic  — Noto Sans (OFL)
  Ethiopic (Amharic, Tigrinya, Oromo in Ge'ez)  — Noto Sans Ethiopic (OFL)
  Hebrew                                       — Noto Sans Hebrew (OFL)
  Arabic (shaped forms, drawn right to left)   — DejaVu Sans (Bitstream Vera licence)
Layout tables and hinting are dropped (the PDF writer doesn't use them).

  pip install fonttools
  python build-fonts.py <folder with the source TTFs> <output folder>
"""
import sys
from fontTools import subset
from fontTools.merge import Merger
from fontTools.ttLib import TTFont
from fontTools.ttLib.scaleUpem import scale_upem

src, out = sys.argv[1], sys.argv[2]
R = lambda a, b: list(range(a, b + 1))
LATIN = R(0x20, 0x7E) + R(0xA0, 0x24F) + R(0x1E00, 0x1EFF) + R(0x2000, 0x206F) + R(0x20A0, 0x20C0) + R(0x2100, 0x214F) + R(0x2190, 0x2193) + R(0x2212, 0x2212) + [0x25CF, 0xFB01, 0xFB02]
GREEK_CYRILLIC = R(0x370, 0x3FF) + R(0x400, 0x52F)
ETHIOPIC = R(0x1200, 0x139F) + R(0x2D80, 0x2DDF) + R(0xAB00, 0xAB2F)
HEBREW = R(0x591, 0x5F4)
ARABIC = R(0x60C, 0x60C) + [0x640] + R(0x61B, 0x61F) + R(0x660, 0x66D) + R(0x6F0, 0x6F9) + R(0xFB50, 0xFDFF) + R(0xFE70, 0xFEFC)

def part(path, codepoints, name):
    font = TTFont(path)
    if font['head'].unitsPerEm != 1000:
        scale_upem(font, 1000)
    opts = subset.Options()
    opts.layout_features = []
    opts.hinting = False
    opts.drop_tables += ['GSUB', 'GPOS', 'GDEF', 'BASE', 'JSTF', 'MATH', 'kern', 'DSIG', 'FFTM', 'STAT', 'MVAR', 'HVAR', 'vhea', 'vmtx', 'gasp']
    opts.name_IDs = ['*']
    opts.notdef_outline = True
    s = subset.Subsetter(opts)
    s.populate(unicodes=codepoints)
    s.subset(font)
    os2 = font['OS/2']
    if os2.version < 4:  # the Merger needs the same OS/2 version everywhere
        os2.version = 4
        for attr, value in (('ulCodePageRange1', 1), ('ulCodePageRange2', 0), ('sxHeight', 1100 // 2), ('sCapHeight', 730),
                            ('usDefaultChar', 0), ('usBreakChar', 32), ('usMaxContext', 1)):
            if not hasattr(os2, attr):
                setattr(os2, attr, value)
    font.save(f'{out}/{name}')
    return f'{out}/{name}'

for weight, dv in (('Regular', 'DejaVuSans.ttf'), ('Bold', 'DejaVuSans-Bold.ttf')):
    parts = [
        part(f'{src}/NotoSans-{weight}.ttf', LATIN + GREEK_CYRILLIC, f'p1-{weight}.ttf'),
        part(f'{src}/NotoSansEthiopic-{weight}.ttf', ETHIOPIC, f'p2-{weight}.ttf'),
        part(f'{src}/NotoSansHebrew-{weight}.ttf', HEBREW, f'p3-{weight}.ttf'),
        part(f'{src}/{dv}', ARABIC, f'p4-{weight}.ttf'),
    ]
    merged = Merger().merge(parts)
    names = merged['name']
    for rec in list(names.names):
        if rec.nameID in (1, 3, 4, 6, 16, 17):
            names.removeNames(nameID=rec.nameID)
    family = 'MenuPdfSans'
    names.setName(family, 1, 3, 1, 0x409)
    names.setName(weight, 2, 3, 1, 0x409)
    names.setName(f'{family} {weight}', 3, 3, 1, 0x409)
    names.setName(f'{family} {weight}', 4, 3, 1, 0x409)
    names.setName(f'{family}-{weight}', 6, 3, 1, 0x409)
    merged.save(f'{out}/{family}-{weight}.ttf')
    print(weight, 'glyphs', len(merged.getGlyphOrder()), 'cmap', len(merged.getBestCmap()))
