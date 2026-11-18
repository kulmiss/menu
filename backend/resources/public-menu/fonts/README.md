# Guest-menu fonts

Only style H · Arabic loads a web font, for the Arabic letters of its headings (owner, 2026-11-18).
Latin letters use the phone's own bold font: a Latin Kufi file was tried and cost about 300 ms of
"largest paint" in the CI speed check (QUESTIONS #231), so a menu in English, Somali or Amharic
downloads no font at all.

| File | Font | Licence | Subset |
|---|---|---|---|
| `kufi-arabic-700.woff2` | Noto Kufi Arabic Bold (Google, via Fontsource 5.3.0) | SIL Open Font License 1.1 (`OFL.txt`) | Arabic letters, Persian/Urdu extras, Arabic and Eastern digits, harakat, joiners |

Made with fonttools:

```
pyftsubset noto-kufi-arabic-arabic-700-normal.woff2 --flavor=woff2 --no-hinting --desubroutinize \
  --unicodes="U+0020-007E,U+00A0,U+060C,U+061B,U+061F,U+0621-063A,U+0640-0652,U+0660-0669,U+066A-066C,U+0670,U+067E,U+0686,U+06A4,U+06A9,U+06AF,U+06CC,U+06F0-06F9,U+200C-200F,U+2010-2014,U+2018-201D" \
  --layout-features='init,medi,fina,isol,rlig,liga,calt,ccmp,mark,mkmk,kern'
```

The page writes it to `public/menu-assets/` with a content hash in the name (`InlineAssets::css()`),
so it is cached for a year. `font-display: optional`: the phone's own font is used when the file is not
ready at once (no wait, no layout shift).
