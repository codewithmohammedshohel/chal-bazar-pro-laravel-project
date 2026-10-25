@extends('layouts.storefront')

@section('title', 'কার্ট | Chal Bazar Pro')

@section('content')
    <div class="mx-auto max-w-4xl">
        <h1 class="text-2xl font-extrabold text-stone-900 sm:text-3xl">আপনার কার্ট</h1>

        @if (session('status'))
            <p class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                {{ session('status') }}
            </p>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if (empty($lines))
            <div class="mt-8 rounded-3xl border border-dashed border-stone-300 bg-white p-10 text-center">
                <p class="text-lg font-semibold text-stone-800">আপনার কার্ট খালি</p>
                <p class="mt-2 text-sm text-stone-500">পছন্দের চাল বা নিত্যপণ্য যোগ করে শুরু করুন।</p>
                <a href="{{ route('home') }}"
                   class="mt-5 inline-block rounded-full bg-stone-900 px-5 py-2 text-sm font-semibold text-white hover:bg-stone-700">
                    কেনাকাটা শুরু করুন
                </a>
            </div>
        @else
            <div class="mt-6 overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-stone-100 text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-4 py-3">পণ্য</th>
                            <th class="px-4 py-3">একক দাম</th>
                            <th class="px-4 py-3">পরিমাণ</th>
                            <th class="px-4 py-3 text-right">মোট</th>
                            <th class="px-4 py-3"><span class="sr-only">সরান</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            @php($variant = $line['variant'])
                            <tr class="border-t border-stone-100 align-top">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-stone-900">{{ $variant->product->displayTitle() }}</p>
                                    <p class="mt-1 text-xs text-stone-500">
                                        {{ $variant->displayLabel() }} · {{ $variant->weightLabel() }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-stone-700">৳ {{ number_format($line['unit_price'], 2) }}</td>
                                <td class="px-4 py-4">
                                    <form method="POST" action="{{ route('cart.update', $variant) }}" class="flex items-center gap-2">
                                        @csrf
                                        <label for="qty-{{ $variant->id }}" class="sr-only">পরিমাণ</label>
                                        <input id="qty-{{ $variant->id }}" type="number" name="quantity"
                                               value="{{ $line['quantity'] }}" min="0" max="100"
                                               class="w-20 rounded-lg border border-stone-300 px-2 py-1 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
                                        <button type="submit" class="rounded-lg border border-stone-300 px-2 py-1 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                                            আপডেট
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-4 text-right font-semibold text-stone-900">৳ {{ number_format($line['line_total'], 2) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <form method="POST" action="{{ route('cart.remove', $variant) }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-500">সরান</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="flex flex-wrap items-center justify-between gap-4 border-t border-stone-200 bg-stone-50 px-4 py-4">
                    <p class="text-sm text-stone-600">
                        সাবটোটাল: <span class="text-lg font-extrabold text-stone-900">৳ {{ number_format($subtotal, 2) }}</span>
                        <span class="ml-2 text-xs text-stone-500">ডেলিভারি চার্জ চেকআউটে হিসাব হবে।</span>
                    </p>
                    <a href="{{ route('checkout.show') }}"
                       class="rounded-full bg-amber-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-300">
                        চেকআউট করুন
                    </a>
                </div>
            </div>
        @endif
    </div>
@endsection
