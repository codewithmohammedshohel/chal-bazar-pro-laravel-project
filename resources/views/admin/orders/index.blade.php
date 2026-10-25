@extends('layouts.storefront')

@section('title', 'অর্ডার ব্যবস্থাপনা | Chal Bazar Pro')

@section('content')
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-extrabold text-stone-900 sm:text-3xl">অর্ডার ব্যবস্থাপনা</h1>
            <form method="GET" class="flex items-center gap-2 text-sm">
                <label for="status" class="sr-only">স্ট্যাটাস</label>
                <select id="status" name="status" class="rounded-xl border border-stone-300 px-3 py-2 text-sm">
                    <option value="">সব</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                    @endforeach
                </select>
                <button class="rounded-full bg-stone-900 px-4 py-2 text-xs font-semibold text-white hover:bg-stone-700">ফিল্টার</button>
            </form>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</p>
        @endif

        <div class="mt-6 overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-stone-100 text-xs uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-4 py-3">অর্ডার</th>
                        <th class="px-4 py-3">গ্রাহক</th>
                        <th class="px-4 py-3">স্ট্যাটাস</th>
                        <th class="px-4 py-3">আইটেম</th>
                        <th class="px-4 py-3 text-right">মোট</th>
                        <th class="px-4 py-3 text-right">তারিখ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="border-t border-stone-100">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-mono font-semibold text-stone-900 hover:text-amber-700">{{ $order->code }}</a>
                            </td>
                            <td class="px-4 py-3 text-stone-600">{{ $order->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-700">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            </td>
                            <td class="px-4 py-3 text-stone-600">{{ $order->items_count }}</td>
                            <td class="px-4 py-3 text-right font-semibold">৳ {{ number_format((float) $order->total, 2) }}</td>
                            <td class="px-4 py-3 text-right text-xs text-stone-500">{{ $order->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-stone-500">কোনো অর্ডার নেই।</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    </div>
@endsection
