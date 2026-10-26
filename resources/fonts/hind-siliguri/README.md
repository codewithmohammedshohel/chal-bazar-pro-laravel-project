# Hind Siliguri — bundled Bengali font

These files are vendored so that server-generated PDF invoices always render
Bengali text correctly, without any network access at render time.

| File | Purpose |
| --- | --- |
| `HindSiliguri-Regular.ttf` | Regular (400) text |
| `HindSiliguri-Bold.ttf` | Bold (700) headings and totals |
| `OFL.txt` | SIL Open Font License 1.1 |

## Source

- Family: **Hind Siliguri**
- Designer / foundry: **Indian Type Foundry**
- Upstream repository: <https://github.com/itfoundry/hind-siliguri>
- Distribution used for download: Google Fonts
  (<https://github.com/google/fonts/tree/main/ofl/hindsiliguri>)
- Downloaded: October 2026 (`main` branch)

## Licence

SIL Open Font License, Version 1.1. The full licence text is in `OFL.txt`.
The OFL permits embedding and redistribution of the font with software,
provided the font is not sold on its own and the licence notice is kept.

## Integrity

| File | SHA-256 |
| --- | --- |
| `HindSiliguri-Regular.ttf` | `45071a7da902a74877f432722f525d00502d61aef71fca4cab3da8a23156c33d` |
| `HindSiliguri-Bold.ttf` | `d135765781d1a4aebc7dd4a2af221427ec8689060121d8a48b19bca3eeb3977e` |
| `OFL.txt` | `4b255eb44f613a8e426e195f0d0b7e10fcba1af7eb6047d9a7f4f3c449940e19` |

## Why this font and not Noto Sans Bengali

`Noto Sans Bengali` is a valid OFL font, but mPDF 8.3.1 (the selected PDF
engine) fails to parse its OpenType layout tables: the renderer exhausts
memory while reading the GSUB coverage tables and aborts the request.

`Hind Siliguri` was verified with a real render: conjuncts (`ক্ষ`, `ত্র`,
`ষ্ঠ`, `ন্ধ`), vowel signs (`ি`, `ে`, `ো`), hasant forms (`শুদ্ধ`, `কৃষ্ণ`) and
Bengali digits (`০১২৩৪৫৬৭৮৯`) are shaped correctly, including mixed
Bangla/English lines and the `৳` currency sign.

Neither font is downloaded at runtime; both weights are embedded into the
generated PDF as a subset.
