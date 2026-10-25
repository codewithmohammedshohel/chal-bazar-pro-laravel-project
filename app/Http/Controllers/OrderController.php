<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest()
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['items', 'statusEvents', 'invoice']);

        return view('orders.show', compact('order'));
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('cancelAsCustomer', $order);

        $this->orders->cancelByCustomer($order, $request->user());

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'অর্ডার বাতিল করা হয়েছে এবং স্টক রিজার্ভেশন ছেড়ে দেওয়া হয়েছে।');
    }
}
