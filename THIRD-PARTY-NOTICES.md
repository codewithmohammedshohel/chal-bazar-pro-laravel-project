# Third-party notices

This repository contains the Chal Bazar Pro application plus third-party
libraries and a bundled font. This file records the licences that matter for
distribution. It is a factual inventory, not legal advice.

## 1. The application source

The application source in this repository is **not released under an
open-source licence**. `composer.json` declares `"license": "proprietary"`.
No `LICENSE` file grants permission to copy, modify, redistribute or resell the
application. All rights are reserved by the project owner.

The `proprietary` value describes the application's own code only. It does not
override the licences of the third-party components listed below. In
particular, this repository depends on mPDF, which is distributed under the GNU
GPL family of licences (see section 2).

## 2. mPDF (PDF invoice renderer)

| Item | Value |
| --- | --- |
| Package | `mpdf/mpdf` |
| Installed version | 8.3.1 |
| Licence declared by the installed package (`vendor/mpdf/mpdf/composer.json`) | `GPL-2.0-only` |
| Licence text bundled with the package (`vendor/mpdf/mpdf/LICENSE.txt`) | GNU General Public License, version 2 |
| Upstream README | "released under the GNU GPL v2 licence" |
| Official mPDF manual, License page (<https://mpdf.github.io/about-mpdf/license.html>) | "either version 2 of the License, or (at your option) any later version" |
| Copyright | Copyright (C) 2010 Ian Back |

There is a genuine metadata mismatch: the Composer package metadata and the
bundled licence file point to GPL-2.0-only, while the upstream manual describes
GPL-2.0-or-later. This notice records the mismatch instead of choosing a
definitive interpretation. Treat mPDF as GPL-2.0-family software until the
maintainer or a lawyer resolves which option applies.

### What this means in practice

- **Running the shop on your own hosting** is use, not distribution. The GPL
  obligations are triggered by distributing copies of the software (for
  example shipping the source, a built package, a Docker image, or handing the
  application to a client), not by letting customers use the website.
- **Distributing the application together with mPDF** creates a combined
  distribution. The safe options are:
  1. comply with the GPL for the combined distribution (provide the complete
     corresponding source and licence notices);
  2. obtain a commercial/alternative licence from the mPDF maintainer;
  3. replace mPDF with a permissively licensed renderer that still shapes
     Bengali correctly.
- **Reselling the software itself** (as opposed to selling rice through the
  website) must not be described as a closed-source proprietary product while
  it still includes mPDF, unless a separate licence is obtained.
- **Attribution:** keep mPDF's copyright and licence notice with any
  distribution that includes the library. If you distribute the application
  without the `vendor/` directory, include this notice and the mPDF licence
  text (or its GPL link) with the package.

The PDF engine is isolated behind `App\Contracts\InvoicePdfRenderer`
(`App\Services\Invoices\MpdfInvoicePdfRenderer`), so it can be replaced later
without changing the controller, routes, views or invoice numbering.

## 3. Hind Siliguri font

| Item | Value |
| --- | --- |
| Files | `resources/fonts/hind-siliguri/HindSiliguri-Regular.ttf`, `HindSiliguri-Bold.ttf` |
| Copyright | Copyright (c) 2015 Indian Type Foundry (info@indiantypefoundry.com) |
| Licence | SIL Open Font License, Version 1.1 |
| Licence text | `resources/fonts/hind-siliguri/OFL.txt` |
| Upstream | <https://github.com/itfoundry/hind-siliguri> (obtained via Google Fonts) |

The bundled copyright notice does not declare a Reserved Font Name. The OFL
permits embedding and redistribution with software, provided the font is not
sold on its own and the copyright/licence notice is retained. Keep `OFL.txt`
and the font attribution in any distribution that includes the font.

The font is embedded as a subset in generated invoice PDFs. No font is
downloaded at runtime and the host does not need the font installed.

## 4. Other dependencies

The PHP dependencies are listed in `composer.lock`; the front-end dependencies
are listed in `package-lock.json`. Most are MIT-licensed (for example Laravel
13 and Livewire 4). Their licence files are present in `vendor/` after
`composer install` and in `node_modules/` after `npm install`, and those
directories are intentionally excluded from Git.

Before distributing the application, generate a complete dependency licence
inventory from the lock files (for example with a licence-audit tool) and keep
the notice files that the distributed packages require.

## 5. Repository contents that are not distributed

- `.env` is not committed and must never be published.
- SQLite databases, generated PDFs, logs, caches, `vendor/`, `node_modules/`
  and `public/build/` are excluded from Git.
- Invoice PDFs are generated on demand and streamed; no customer invoice file
  is stored in the repository.
