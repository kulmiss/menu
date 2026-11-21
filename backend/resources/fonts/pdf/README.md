# PDF fonts

`MenuPdfSans-Regular.ttf` and `MenuPdfSans-Bold.ttf` are used **only for the
printable PDFs** (menu, table cards, print materials, invoices, Insights
report) when a text has letters the built-in PDF fonts can't show. The website
never loads them. QUESTIONS #205.

Each file joins parts of open-licence fonts (about 210 KB; a PDF embeds only
the letters it uses):

| Letters | From | Licence |
|---|---|---|
| Latin (with Vietnamese), Greek, Cyrillic | Noto Sans | SIL Open Font License 1.1 (`OFL-Noto.txt`) |
| Ethiopic (Amharic, Tigrinya, Afaan Oromo in Ge'ez) | Noto Sans Ethiopic | SIL Open Font License 1.1 |
| Hebrew | Noto Sans Hebrew | SIL Open Font License 1.1 |
| Arabic, Persian, Urdu letters (joined forms) | DejaVu Sans | Bitstream Vera licence (`LICENSE-DejaVu.txt`), renamed as it requires |

Not covered (left out of PDFs, like emoji): Chinese, Japanese, Korean,
Indian and Southeast Asian scripts. They need very large fonts or a shaping
engine the PDF writer doesn't have.

To rebuild (for example to add letters):

```
pip install fonttools
python build-fonts.py <folder with NotoSans-*.ttf, NotoSansEthiopic-*.ttf, NotoSansHebrew-*.ttf, DejaVuSans*.ttf> .
```

Sources: https://notofonts.github.io and https://dejavu-fonts.github.io
