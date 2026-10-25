<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        $this->authorize('viewInvoice', $order);

        $order->load(['items', 'invoice', 'user']);

        abort_if($order->invoice === null, 404);

        return view('orders.invoice', compact('order'));
    }
}
