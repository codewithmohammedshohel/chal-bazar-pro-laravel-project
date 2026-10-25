<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly DeliveryFeeService $delivery,
        private readonly OrderService $orders,
    ) {
    }

    public function show(Request $request): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'চেকআউটের আগে কার্টে পণ্য যোগ করুন।']);
        }

        $idempotencyKey = (string) $request->session()->get('checkout.idempotency_key', '');

        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
            $request->session()->put('checkout.idempotency_key', $idempotencyKey);
        }

        return view('checkout.show', [
            'lines' => $this->cart->items(),
            'shopLat' => $this->delivery->shopLat(),
            'shopLng' => $this->delivery->shopLng(),
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'কার্ট খালি।']);
        }

        $validated = $request->validate([
            'delivery_address' => ['required', 'string', 'min:10', 'max:1000'],
            'delivery_lat' => ['required', 'numeric', 'between:-90,90'],
            'delivery_lng' => ['required', 'numeric', 'between:-180,180'],
            'customer_note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $key = $validated['idempotency_key'] ?? null;

        // Only trust a key that this session actually issued.
        $expectedKey = $request->session()->get('checkout.idempotency_key');

        if ($key === null || $expectedKey === null || ! hash_equals((string) $expectedKey, (string) $key)) {
            $key = (string) Str::uuid();
            $request->session()->put('checkout.idempotency_key', $key);
        }

        $order = $this->orders->place(
            customer: $request->user(),
            items: $this->cart->toPricingItems(),
            deliveryAddress: $validated['delivery_address'],
            lat: (float) $validated['delivery_lat'],
            lng: (float) $validated['delivery_lng'],
            customerNote: $validated['customer_note'] ?? null,
            idempotencyKey: $key,
        );

        $this->cart->clear();
        $request->session()->forget('checkout.idempotency_key');

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'আপনার অর্ডার সফলভাবে গ্রহণ করা হয়েছে।');
    }
}
