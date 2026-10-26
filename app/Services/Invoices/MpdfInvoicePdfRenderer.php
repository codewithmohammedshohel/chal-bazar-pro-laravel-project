<?php

namespace App\Services\Invoices;

use App\Contracts\InvoicePdfRenderer;
use App\Models\Order;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use Throwable;

/**
 * Renders the issued invoice as a PDF with mPDF and an embedded Bengali font.
 *
 * The document is produced on demand from the stored invoice and order-item
 * snapshots. Nothing is persisted: no invoice number is reserved, no file is
 * written to the public or private disk and no order/stock column is touched.
 */
final class MpdfInvoicePdfRenderer implements InvoicePdfRenderer
{
    public function render(Order $order): string
    {
        if (! class_exists(Mpdf::class)) {
            throw new RuntimeException('The PDF rendering library is not installed.');
        }

        $tempDir = (string) config('invoice.pdf.temp_dir');

        try {
            File::ensureDirectoryExists($tempDir, 0775, true);

            $mpdf = $this->makeMpdf($tempDir);
            $mpdf->SetTitle('Invoice '.($order->invoice?->invoice_number ?? $order->code));
            $mpdf->SetAuthor((string) config('invoice.business.name'));
            $mpdf->SetCreator((string) config('invoice.business.name'));
            $mpdf->WriteHTML($this->html($order));

            return $mpdf->Output('invoice.pdf', Destination::STRING_RETURN);
        } catch (Throwable $e) {
            // Keep the original exception as the cause for local debugging,
            // but never expose rendering internals to the HTTP layer.
            throw new RuntimeException('Invoice PDF could not be rendered.', 0, $e);
        }
    }

    /**
     * The HTML document that is handed to mPDF.
     *
     * Exposed separately so tests (and future PDF engines) can assert the
     * historical invoice content without parsing PDF binary streams.
     */
    public function html(Order $order): string
    {
        $order->loadMissing(['items', 'invoice', 'user']);

        $invoice = $order->invoice;

        if ($invoice === null) {
            throw new RuntimeException('The order has no issued invoice.');
        }

        return view('invoices.pdf', [
            'order' => $order,
            'invoice' => $invoice,
            'items' => $order->items->sortBy('id')->values(),
            'business' => (array) config('invoice.business', []),
            'fontFamily' => (string) config('invoice.pdf.font_family'),
        ])->render();
    }

    private function makeMpdf(string $tempDir): Mpdf
    {
        $defaultConfig = (new ConfigVariables)->getDefaults();
        $defaultFontConfig = (new FontVariables)->getDefaults();

        $fontFamily = (string) config('invoice.pdf.font_family');

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => (string) config('invoice.pdf.paper', 'A4'),
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 12,
            'margin_bottom' => 16,
            'margin_footer' => 8,
            'tempDir' => $tempDir,
            'fontDir' => array_merge($defaultConfig['fontDir'], [(string) config('invoice.pdf.font_dir')]),
            'fontdata' => $defaultFontConfig['fontdata'] + [
                $fontFamily => [
                    'R' => 'HindSiliguri-Regular.ttf',
                    'B' => 'HindSiliguri-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => $fontFamily,
        ]);

        /*
         * mPDF's automatic language detection must stay disabled here.
         *
         * `autoLangToFont` maps the document language to one of mPDF's bundled
         * fonts (its LanguageToFont table resolves every Bengali tag, "bn"
         * included, to FreeSerif) and that override wins over the
         * `font-family` set in the stylesheet. The result was a PDF that
         * embedded FreeSerif and silently lost Bengali shaping.
         *
         * This document is single-script: Hind Siliguri shapes the Bengali
         * text and also carries the Latin glyphs used for codes and e-mail
         * addresses, so the font is selected explicitly instead.
         */
        $mpdf->autoScriptToLang = false;
        $mpdf->autoLangToFont = false;

        return $mpdf;
    }
}
