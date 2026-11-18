# Guest-menu fonts

Only style H · Arabic loads a web font, for its headings (owner, 2026-11-18).

| File | Font | Licence | Subset |
|---|---|---|---|
| `kufi-arabic-700.woff2` | Noto Kufi Arabic Bold (Google, via Fontsource 5.3.0) | SIL Open Font License 1.1 (`OFL.txt`) | Arabic letters, Persian/Urdu extras, Arabic and Eastern digits, harakat, joiners |
| `kufi-latin-700.woff2` | Noto Kufi Arabic Bold, Latin part | SIL Open Font License 1.1 | Basic Latin, Latin-1, quotes, dashes |

Made with fonttools:

```
pyftsubset noto-kufi-arabic-arabic-700-normal.woff2 --flavor=woff2 --no-hinting --desubroutinize \
  --unicodes="U+0020-007E,U+00A0,U+060C,U+061B,U+061F,U+0621-063A,U+0640-0652,U+0660-0669,U+066A-066C,U+0670,U+067E,U+0686,U+06A4,U+06A9,U+06AF,U+06CC,U+06F0-06F9,U+200C-200F,U+2010-2014,U+2018-201D" \
  --layout-features='init,medi,fina,isol,rlig,liga,calt,ccmp,mark,mkmk,kern'
pyftsubset noto-kufi-arabic-latin-700-normal.woff2 --flavor=woff2 --no-hinting \
  --unicodes="U+0020-007E,U+00A0-00FF,U+2013-2014,U+2018-201D,U+2022,U+20AC" --layout-features='kern,liga'
```

The page writes them to `public/menu-assets/` with a content hash in the name (`InlineAssets::css()`),
so they are cached for a year. `font-display: optional`: the phone's own font is used when the file is not
ready at once (no wait, no layout shift).
