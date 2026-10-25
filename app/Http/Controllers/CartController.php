<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart)
    {
    }

    public function index(): View
    {
        $lines = $this->cart->items();

        return view('cart.index', [
            'lines' => $lines,
            'subtotal' => round(array_sum(array_column($lines, 'line_total')), 2),
        ]);
    }

    public function add(Request $request, ProductVariant $variant): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ]);

        return $this->addToCart($variant, (int) $validated['quantity']);
    }

    public function addItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ]);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);

        return $this->addToCart($variant, (int) $validated['quantity']);
    }

    private function addToCart(ProductVariant $variant, int $quantity): RedirectResponse
    {
        if (! $variant->is_active || ! $variant->product?->is_active) {
            return back()->withErrors(['quantity' => 'এই পণ্যটি এখন বিক্রয়ের জন্য উপলব্ধ নয়।']);
        }

        $this->cart->add($variant->id, $quantity);

        return redirect()
            ->route('cart.index')
            ->with('status', 'কার্টে যোগ করা হয়েছে।');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_QUANTITY],
        ]);

        $this->cart->update($variant->id, (int) $validated['quantity']);

        return redirect()->route('cart.index')->with('status', 'কার্ট আপডেট হয়েছে।');
    }

    public function remove(ProductVariant $variant): RedirectResponse
    {
        $this->cart->remove($variant->id);

        return redirect()->route('cart.index')->with('status', 'পণ্য কার্ট থেকে সরানো হয়েছে।');
    }
}
