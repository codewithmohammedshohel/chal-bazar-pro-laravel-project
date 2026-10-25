<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $status = (string) $request->query('status', '');

        $orders = Order::query()
            ->with('user')
            ->withCount('items')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'statuses' => [
                Order::STATUS_PENDING,
                Order::STATUS_ACCEPTED,
                Order::STATUS_IN_TRANSIT,
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
            ],
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('viewAny', Order::class);

        $order->load(['items', 'statusEvents.actor', 'invoice', 'user']);

        return view('admin.orders.show', compact('order'));
    }

    public function transition(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Order::STATUS_ACCEPTED,
                Order::STATUS_IN_TRANSIT,
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
            ])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['status'] === Order::STATUS_CANCELLED) {
            $this->authorize('cancelAsShop', $order);
            $this->orders->cancelByShop($order, $request->user(), $validated['note'] ?? null);

            return back()->with('status', 'অর্ডার বাতিল হয়েছে এবং প্রয়োজনীয় রিজার্ভেশন ছেড়ে দেওয়া হয়েছে।');
        }

        $this->authorize('transition', $order);
        $this->orders->transition($order, $validated['status'], $request->user(), $validated['note'] ?? null);

        return back()->with('status', 'অর্ডার স্ট্যাটাস আপডেট হয়েছে।');
    }

    public function recordReturn(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('recordReturn', $order);

        $validated = $request->validate([
            'product_variant_id' => [
                'required',
                'integer',
                Rule::exists('order_items', 'product_variant_id')->where('order_id', $order->id),
            ],
            'quantity_kg' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'operation_key' => ['required', 'string', 'max:80'],
        ]);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);

        $this->orders->recordReturn(
            $order,
            $request->user(),
            [(int) $variant->id => (float) $validated['quantity_kg']],
            $validated['operation_key'],
        );

        return back()->with('status', 'ফেরত পণ্য স্টকে যোগ করা হয়েছে।');
    }
}
