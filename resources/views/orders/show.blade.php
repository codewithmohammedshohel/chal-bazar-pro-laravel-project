@extends('layouts.storefront')

@section('title', 'অর্ডার '.$order->code.' | Chal Bazar Pro')

@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">অর্ডার</p>
                <h1 class="mt-1 font-mono text-2xl font-extrabold text-stone-900">{{ $order->code }}</h1>
                <p class="mt-1 text-sm text-stone-500">{{ $order->created_at->format('d M Y, h:i A') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('orders.invoice', $order) }}" target="_blank" rel="noopener"
                   class="rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                    ইনভয়েস (প্রিন্ট)
                </a>
                @if ($order->invoice)
                    <a href="{{ route('orders.invoice.pdf', $order) }}"
                       class="rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                        ইনভয়েস PDF
                    </a>
                    <span class="rounded-full bg-stone-100 px-3 py-2 text-xs font-mono text-stone-600">{{ $order->invoice->invoice_number }}</span>
                @endif
                @if (! empty($whatsappUrl))
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"
                       class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                        WhatsApp-এ কনফার্ম করুন
                    </a>
                @endif
            </div>
        </div>

        @if (! empty($whatsappUrl))
            <p class="mt-3 rounded-2xl bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-900">
                WhatsApp বাটনটি শুধু একটি বার্তা প্রস্তুত করে WhatsApp অ্যাপ খুলবে। আপনি নিজে যাচাই করে পাঠানোর বোতাম চাপলে বার্তাটি যাবে।
            </p>
        @endif

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

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-stone-900">পণ্যসমূহ</h2>
                <ul class="mt-4 divide-y divide-stone-100">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3 py-3 text-sm">
                            <span>
                                <span class="font-semibold text-stone-900">{{ $item->product_title }}</span>
                                <span class="block text-xs text-stone-500">{{ $item->variant_label }} · {{ $item->weight_kg }} kg × {{ $item->quantity }}</span>
                            </span>
                            <span class="font-semibold text-stone-900">৳ {{ number_format((float) $item->line_total, 2) }}</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-4 space-y-2 border-t border-stone-200 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">সাবটোটাল</dt><dd class="font-medium">৳ {{ number_format((float) $order->subtotal, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">ডেলিভারি চার্জ</dt><dd class="font-medium">৳ {{ number_format((float) $order->delivery_fee, 2) }}</dd></div>
                    <div class="flex justify-between border-t border-stone-200 pt-2 text-base"><dt class="font-bold">মোট</dt><dd class="font-extrabold">৳ {{ number_format((float) $order->total, 2) }}</dd></div>
                </dl>
            </section>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-stone-900">স্ট্যাটাস</h2>
                    <ol class="mt-4 space-y-4">
                        @foreach ($order->statusEvents as $event)
                            <li class="flex gap-3">
                                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
                                <div>
                                    <p class="text-sm font-semibold text-stone-900">{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</p>
                                    @if ($event->note)
                                        <p class="text-xs text-stone-500">{{ $event->note }}</p>
                                    @endif
                                    <p class="text-[11px] text-stone-400">{{ $event->created_at->format('d M Y, h:i A') }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-stone-900">ডেলিভারি</h2>
                    <p class="mt-3 text-sm leading-6 text-stone-600">{{ $order->delivery_address }}</p>
                    @if ($order->distance_km !== null)
                        <p class="mt-2 text-xs text-stone-500">দূরত্ব: {{ $order->distance_km }} কিমি</p>
                    @endif
                    @if ($order->customer_note)
                        <p class="mt-3 rounded-xl bg-stone-50 p-3 text-xs text-stone-600">নোট: {{ $order->customer_note }}</p>
                    @endif
                    <p class="mt-3 text-xs text-stone-500">পেমেন্ট: ক্যাশ অন ডেলিভারি</p>
                </section>

                @if ($order->status === 'pending' || $order->status === 'accepted')
                    <form method="POST" action="{{ route('orders.cancel', $order) }}"
                          onsubmit="return confirm('আপনি কি এই অর্ডারটি বাতিল করতে চান?');">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-full border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                            অর্ডার বাতিল করুন
                        </button>
                    </form>
                @endif
            </aside>
        </div>
    </div>
@endsection
