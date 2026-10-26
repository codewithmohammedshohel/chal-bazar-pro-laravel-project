# Invoice PDF & WhatsApp order confirmation

This document records how the server-generated Bangla invoice PDF and the
WhatsApp click-to-chat confirmation work, why they are built this way, and what
still needs to be decided before the code is redistributed.

## 1. PDF engine decision

Candidates were evaluated against Laravel 13 / PHP 8.4, real Bengali shaping,
print layout, licensing, cPanel shared-hosting constraints and memory use.
Each candidate was rendered with the *same* Bangla test document
(conjuncts, vowel signs, hasant forms, Bengali digits, `৳`, mixed
Bangla/English) and the resulting PDF was rasterised and inspected.

| Engine | Licence | Bengali result | Notes |
| --- | --- | --- | --- |
| **mPDF 8.3.1 + Hind Siliguri** | GPL-2.0-only | **Correct** | Conjuncts, vowel signs, Bengali digits and Latin all shaped correctly. ~0.5 s and ~18 MB peak for a one-page A4 invoice on a cold font cache; the PDF was ~37 KB. Requires only `ext-gd` and `ext-mbstring`. |
| Dompdf 3.1.6 + Hind Siliguri | LGPL-2.1 | **Corrupt** | No OpenType GSUB/GPOS shaping. `ক্ষ` renders as separate glyphs, `ি` keeps its logical (post-consonant) position and several clusters fall back to dotted circles. Rejected: shipping this would produce visibly broken Bangla. |
| mPDF 8.3.1 + Noto Sans Bengali | GPL-2.0-only / OFL font | **Renderer crash** | mPDF's GSUB parser fails on Noto Sans Bengali's layout tables and exhausts memory inside `TTFontFile`. |
| TCPDF / tc-lib-pdf | LGPL-3.0-or-later | Not viable | The TCPDF lineage maps Unicode to glyphs 1:1 and does not implement Indic shaping. Not selected; not render-tested. |

**Selected engine: mPDF 8.3.1**, because it is the only evaluated pure-PHP
engine that produces correct Bengali text and still deploys on shared hosting
without external binaries, a headless browser or a network font service.

The renderer is isolated behind `App\Contracts\InvoicePdfRenderer`
(`App\Services\Invoices\MpdfInvoicePdfRenderer`), so the engine can be replaced
without touching the controller, routes, views or the invoice-numbering code.

### Licence obligation (read before redistribution)

The installed package metadata (`vendor/mpdf/mpdf/composer.json`) declares
`GPL-2.0-only`, and the bundled `LICENSE.txt` is the GNU GPL version 2 text.
The upstream README says "GNU GPL v2 licence", while the official manual's
License page (<https://mpdf.github.io/about-mpdf/license.html>) says "either
version 2 of the License, or (at your option) any later version". The metadata
is inconsistent, so treat mPDF as GPL-2.0-family software and do not assume a
permissive licence.

Running the shop on your own hosting is use, not distribution. Distributing the
application together with mPDF (source, package, container image or client
handover) creates a combined distribution. The owner must choose one of:

1. comply with GPL-2.0 for the combined distribution (ship the complete
   corresponding source and the licence notices),
2. obtain another or commercial mPDF licence from the maintainer, or
3. replace mPDF with a permissively licensed engine that can shape Bengali
   (e.g. a Pango/HarfBuzz-based renderer such as WeasyPrint or headless
   Chromium) — both need binaries that are not guaranteed on cheap cPanel
   plans.

This is a factual inventory, not legal advice. The application source in this
repository is declared `proprietary` in `composer.json` and is not MIT-licensed;
see `THIRD-PARTY-NOTICES.md` for the full notices. That repository declaration
does not override mPDF's GPL terms for a combined distribution.

The PDF feature can also be switched off entirely (`INVOICE_PDF_ENABLED=false`),
in which case the printable HTML invoice remains the only invoice format.

## 2. Bengali font

- **Font:** Hind Siliguri Regular (400) and Bold (700)
- **Source:** Indian Type Foundry, vendored from Google Fonts
  (`ofl/hindsiliguri`), upstream <https://github.com/itfoundry/hind-siliguri>
- **Licence:** SIL Open Font License 1.1 — see
  `resources/fonts/hind-siliguri/OFL.txt`
- **Location:** `resources/fonts/hind-siliguri/`
- **Embedding:** both weights are registered with mPDF through
  `config/invoice.php` and embedded as a subset in every generated PDF. No
  remote font request is made at render time, and the host does not need the
  font installed.

A real render was used to verify shaping, not just the presence of a font file:
conjuncts, vowel-sign reordering, hasant clusters, Bengali digits and mixed
Bangla/English text all rendered correctly.

### Do not re-enable mPDF's automatic language detection

`MpdfInvoicePdfRenderer` sets `autoScriptToLang = false` and
`autoLangToFont = false` deliberately. With these enabled, mPDF's
`LanguageToFont` table resolves every Bengali tag (`bn`, `ben`) to its bundled
**FreeSerif** and overrides the stylesheet `font-family`, so the document was
silently rendered with FreeSerif and lost Bengali shaping even though the
Hind Siliguri font was configured. The invoice is single-script, and Hind
Siliguri also carries the Latin glyphs used for codes and e-mail addresses, so
the font is selected explicitly instead.

If the renderer is ever changed, assert the embedded fonts with a real render:

```php
preg_match_all('~/BaseFont\s*/([A-Za-z0-9+,\-]+)~', $pdf, $m);
// must contain HindSiliguri-Regular / HindSiliguri-Bold, never FreeSerif
```

## 3. Endpoint, authorization and storage

```
GET /orders/{order}/invoice/pdf    orders.invoice.pdf
```

- The route lives inside the `auth` + `active` middleware group, so guests are
  redirected to the login page and suspended/blocked accounts are rejected.
- Authorization always goes through `OrderPolicy::viewInvoice` on the bound
  **order**: the owning customer, or an administrator holding the existing
  `invoices` permission. A raw order/invoice id is never accepted as proof of
  access, and other customers receive `403`.
- The route is read-only. It never issues an invoice number, never writes an
  invoice row and never changes order amounts or stock.
- The PDF is rendered **on demand** and streamed as an attachment
  (`invoice-INV-YYYYMM-NNNNNN.pdf`). No file is written to public or private
  storage, so there is no file-path input, no path traversal surface and no
  duplicate PDF files.
- Responses are sent with `Cache-Control: private, no-store`,
  `X-Content-Type-Options: nosniff` and an explicit `Content-Type`.
- If rendering fails, the exception is logged (order id, invoice id and
  exception class only) and the user receives a generic `503` page that links
  back to the printable HTML invoice. Order, invoice and stock records are left
  untouched.
- `INVOICE_PDF_ENABLED=false` hides the feature and makes the endpoint return
  `404`, while the HTML invoice keeps working.

## 4. Invoice number behaviour

Numbers are still issued exactly once, by `InvoiceSequence::nextNumber()`
during checkout, and stored on `invoices`. The PDF endpoint only reads that
row. Repeated downloads therefore keep the same number and the same historical
content; no new invoice record is created (covered by tests).

## 5. Document content

`resources/views/invoices/pdf.blade.php` renders, in Bengali:

- shop name/brand plus optional phone and address from configuration,
- invoice number, issue date (Bengali digits) and order code,
- customer name, e-mail and delivery address (the document is private),
- line items from the stored `order_items` snapshots
  (`product_title`, `variant_label`, weight, quantity, `unit_price`,
  `line_total`),
- subtotal, delivery fee and total from the issued `invoices` row,
- payment method (COD) and a "computer generated" footer.

All amounts use `App\Support\Bangla` (`৳ ১,২৩৪.৫০`), i.e. the BDT symbol with
Bengali digits and two decimals. Current catalogue values are never used, so a
later rename or price change cannot rewrite an issued invoice.

## 6. WhatsApp click-to-chat

Configuration (`config/whatsapp.php`, all env-backed):

```dotenv
WHATSAPP_ENABLED=true
WHATSAPP_BUSINESS_NUMBER=8801712345678   # canonical international, digits only
WHATSAPP_BUSINESS_NAME="Chal Bazar Pro"
```

- The destination is **never hardcoded** and is not taken from the phone number
  printed on the invoice.
- `OrderConfirmationLink::normalize()` accepts `+880 1712-345678`,
  `8801712345678` and `008801712345678`; local-format values such as
  `01712345678` are rejected so customer messages cannot silently go to a
  wrong/duplicated destination.
- When the value is empty, disabled or invalid, the button is hidden and no URL
  is produced. The required setting is `WHATSAPP_BUSINESS_NUMBER`.
- The link is generated only when the signed-in user is the customer who placed
  the order. Staff/admins never receive a customer-facing link, and cancelled
  orders do not offer one.
- The message is built from server-side records only (order code, up to four
  item titles with quantities, item count, total, COD note). Query-string or
  form values from the browser are ignored, so a forged total cannot change it.
- The message contains no delivery address, e-mail, password, token or other
  secret. It is encoded with `PHP_QUERY_RFC3986` (spaces as `%20`, Bengali as
  UTF-8 percent-encoding).
- The UI states that the button only opens WhatsApp with a prepared message and
  that the customer must review and press send. Nothing is sent by the server;
  the WhatsApp Cloud API and webhooks are out of scope.

## 7. Configuration reference

```dotenv
INVOICE_PDF_ENABLED=true
INVOICE_BUSINESS_NAME="Chal Bazar Pro"
INVOICE_BUSINESS_NAME_BN="চাল বাজার প্রো"
INVOICE_BUSINESS_PHONE=
INVOICE_BUSINESS_ADDRESS=
```

`storage/app/mpdf` is created on demand and is ignored by Git; it only holds
mPDF's temporary font-metric cache and can be cleared at any time.

## 8. How to verify Bengali rendering manually

1. Place or open an order and click **ইনভয়েস PDF** (or call
   `GET /orders/{order}/invoice/pdf` as the owning customer).
2. Open the downloaded PDF in a viewer and confirm the words `ক্ষেত্রফল`,
   `শুদ্ধ`, `কৃষ্ণ`, `ব্রহ্মপুত্র`, `ইনভয়েস` and the amounts `৳ ১,২৩৪.৫০` look
   correct (no dotted circles, no detached `ি` before its consonant).
3. Compare with the printable HTML invoice, which uses the browser's own text
   shaping and is the fallback if PDF rendering is disabled or fails.

Automated tests assert that a real PDF is produced, that the Hind Siliguri font
is embedded, and that the document source contains the historical snapshot
values, Bengali labels and Bengali digits. Glyph-level shaping itself is
verified visually (step 2), because PHP has no built-in PDF text extractor.
