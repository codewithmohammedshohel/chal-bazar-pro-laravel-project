<?php

namespace App\Http\Controllers;

use App\Contracts\InvoicePdfRenderer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class InvoiceController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        $this->authorize('viewInvoice', $order);

        $order->load(['items', 'invoice', 'user']);

        abort_if($order->invoice === null, 404);

        return view('orders.invoice', compact('order'));
    }

    /**
     * Stream the issued invoice as a PDF.
     *
     * Authorization always runs against the order (never against an invoice id
     * supplied by the browser), and the PDF is rendered from the invoice that
     * was issued at checkout. No invoice number is reserved here.
     */
    public function pdf(Request $request, Order $order, InvoicePdfRenderer $renderer): Response
    {
        $this->authorize('viewInvoice', $order);

        $order->loadMissing(['items', 'invoice', 'user']);

        abort_if($order->invoice === null, 404);

        abort_unless((bool) config('invoice.pdf.enabled', true), 404);

        try {
            $pdf = $renderer->render($order);
        } catch (Throwable $e) {
            // Rendering is read-only, so the order, invoice and stock records
            // are untouched. Report a generic failure and keep the internal
            // exception detail out of the response.
            Log::error('Invoice PDF rendering failed.', [
                'order_id' => $order->id,
                'invoice_id' => $order->invoice->id,
                'exception' => $e::class,
            ]);

            return response()->view('orders.invoice-pdf-error', ['order' => $order], 503);
        }

        $filename = 'invoice-'.preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $order->invoice->invoice_number).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"; filename*=UTF-8\'\''.rawurlencode($filename),
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
