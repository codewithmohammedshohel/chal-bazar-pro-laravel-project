<?php

namespace App\Contracts;

use App\Models\Order;

interface InvoicePdfRenderer
{
    /**
     * Render the issued invoice of the given order as PDF bytes.
     *
     * The renderer must never issue an invoice number, write to the database
     * or change stock and order amounts.
     *
     * @throws \RuntimeException when the PDF cannot be produced
     */
    public function render(Order $order): string;
}
