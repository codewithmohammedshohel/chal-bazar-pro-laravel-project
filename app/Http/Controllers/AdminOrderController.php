<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        $order->load([
            'items.returns',
            'statusEvents.actor',
            'returnBatches.actor',
            'invoice',
            'user',
        ]);

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
            'returns' => ['required', 'array', 'min:1'],
            'returns.*' => ['nullable', 'decimal:0,4', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'operation_key' => ['required', 'string', 'max:80'],
        ]);

        $lines = [];

        foreach ($validated['returns'] as $itemId => $quantity) {
            if ($quantity === null || $quantity === '') {
                continue;
            }

            $itemId = (int) $itemId;

            if ($itemId <= 0) {
                throw ValidationException::withMessages([
                    'returns' => 'ফেরতের জন্য সঠিক অর্ডার আইটেম নির্বাচন করুন।',
                ]);
            }

            $lines[] = [
                'order_item_id' => $itemId,
                'quantity_kg' => (string) $quantity,
            ];
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'returns' => 'অন্তত একটি পণ্যের ফেরত পরিমাণ দিন।',
            ]);
        }

        $batch = $this->orders->recordReturn(
            $order,
            $request->user(),
            $lines,
            $validated['operation_key'],
            (string) $validated['reason'],
        );

        return back()->with(
            'status',
            "ফেরত রেকর্ড {$batch->reference} তৈরি হয়েছে এবং যাচাইকৃত পণ্য স্টকে যোগ হয়েছে।",
        );
    }
}
