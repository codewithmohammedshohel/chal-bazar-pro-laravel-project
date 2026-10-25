@extends('layouts.storefront')

@section('title', 'আমার অর্ডার | Chal Bazar Pro')

@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-extrabold text-stone-900 sm:text-3xl">আমার অর্ডার</h1>
            <a href="{{ route('cart.index') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-600">কার্ট দেখুন</a>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                {{ session('status') }}
            </p>
        @endif

        @if ($orders->isEmpty())
            <div class="mt-8 rounded-3xl border border-dashed border-stone-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-stone-800">এখনো কোনো অর্ডার নেই</p>
                <a href="{{ route('home') }}" class="mt-4 inline-block rounded-full bg-stone-900 px-5 py-2 text-sm font-semibold text-white hover:bg-stone-700">
                    কেনাকাটা শুরু করুন
                </a>
            </div>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($orders as $order)
                    <li class="rounded-3xl border border-stone-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <a href="{{ route('orders.show', $order) }}" class="font-mono text-sm font-bold text-stone-900 hover:text-amber-700">
                                    {{ $order->code }}
                                </a>
                                <p class="mt-1 text-xs text-stone-500">
                                    {{ $order->created_at->format('d M Y, h:i A') }} · {{ $order->items_count }} আইটেম
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide',
                                    'bg-amber-100 text-amber-800' => $order->status === 'pending',
                                    'bg-sky-100 text-sky-800' => $order->status === 'accepted',
                                    'bg-indigo-100 text-indigo-800' => $order->status === 'in_transit',
                                    'bg-emerald-100 text-emerald-800' => $order->status === 'delivered',
                                    'bg-red-100 text-red-800' => $order->status === 'cancelled',
                                ])>
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                                <span class="text-lg font-extrabold text-stone-900">৳ {{ number_format((float) $order->total, 2) }}</span>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection
