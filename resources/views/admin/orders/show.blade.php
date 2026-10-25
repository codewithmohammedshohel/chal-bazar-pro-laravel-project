@extends('layouts.storefront')

@section('title', 'অর্ডার '.$order->code.' | Admin')

@section('content')
    <div class="mx-auto max-w-5xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('admin.orders.index') }}" class="text-xs font-semibold text-amber-700 hover:text-amber-600">← সব অর্ডার</a>
                <h1 class="mt-1 font-mono text-2xl font-extrabold text-stone-900">{{ $order->code }}</h1>
                <p class="mt-1 text-sm text-stone-500">{{ $order->user?->name }} · {{ $order->user?->email }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('orders.invoice', $order) }}" target="_blank" rel="noopener"
                   class="rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">ইনভয়েস</a>
                <span class="rounded-full bg-stone-100 px-3 py-2 text-xs font-semibold text-stone-700">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
            </div>
        </div>

        @if (session('status'))
            <p class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-stone-900">আইটেম</h2>
                <ul class="mt-4 divide-y divide-stone-100">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3 py-3 text-sm">
                            <span>
                                <span class="font-semibold text-stone-900">{{ $item->product_title }}</span>
                                <span class="block text-xs text-stone-500">{{ $item->variant_label }} · {{ $item->weight_kg }} kg × {{ $item->quantity }}</span>
                                <span class="mt-1 block text-xs text-stone-500">
                                    পাঠানো: {{ $item->dispatchedWeightLabel() }} ·
                                    ফেরত: {{ $item->returnedWeightLabel() }} ·
                                    বাকি: {{ $item->remainingWeightLabel() }}
                                </span>
                            </span>
                            <span class="font-semibold">৳ {{ number_format((float) $item->line_total, 2) }}</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-4 space-y-2 border-t border-stone-200 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">সাবটোটাল</dt><dd>৳ {{ number_format((float) $order->subtotal, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">ডেলিভারি</dt><dd>৳ {{ number_format((float) $order->delivery_fee, 2) }}</dd></div>
                    <div class="flex justify-between text-base font-extrabold"><dt>মোট</dt><dd>৳ {{ number_format((float) $order->total, 2) }}</dd></div>
                </dl>

                <h2 class="mt-8 text-lg font-bold text-stone-900">স্ট্যাটাস হিস্টোরি</h2>
                <ol class="mt-3 space-y-3 text-sm">
                    @foreach ($order->statusEvents as $event)
                        <li class="rounded-xl bg-stone-50 p-3">
                            <p class="font-semibold text-stone-900">{{ ucfirst(str_replace('_', ' ', $event->to_status)) }}</p>
                            <p class="text-xs text-stone-500">
                                {{ $event->created_at->format('d M Y, h:i A') }}
                                @if ($event->actor) · {{ $event->actor->name }} ({{ $event->actor_role }}) @endif
                            </p>
                            @if ($event->note)<p class="mt-1 text-xs text-stone-600">{{ $event->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-stone-900">স্ট্যাটাস অ্যাকশন</h2>
                    <form method="POST" action="{{ route('admin.orders.transition', $order) }}" class="mt-4 space-y-3">
                        @csrf
                        <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">নতুন স্ট্যাটাস</label>
                        <select id="status" name="status" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                            @foreach ([
                                \App\Models\Order::STATUS_ACCEPTED => 'Accepted',
                                \App\Models\Order::STATUS_IN_TRANSIT => 'In Transit (dispatch)',
                                \App\Models\Order::STATUS_DELIVERED => 'Delivered',
                                \App\Models\Order::STATUS_CANCELLED => 'Cancelled',
                            ] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <input name="note" maxlength="500" placeholder="নোট (ঐচ্ছিক)" class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white hover:bg-stone-700">আপডেট করুন</button>
                    </form>
                    <p class="mt-3 text-xs leading-5 text-stone-500">
                        Dispatch করলে ফিজিক্যাল স্টক কমবে। বাতিল করলে শুধু পেন্ডিং রিজার্ভেশন ছাড়া হবে।
                    </p>
                </section>

                <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-stone-900">ফেরত পণ্য</h2>

                    @can('recordReturn', $order)
                        <form method="POST" action="{{ route('admin.orders.return', $order) }}" class="mt-4 space-y-3">
                            @csrf
                            @foreach ($order->items as $item)
                                <div class="rounded-xl border border-stone-200 p-3">
                                    <label for="return-{{ $item->id }}" class="block text-xs font-semibold text-stone-700">
                                        {{ $item->product_title }} — {{ $item->variant_label }}
                                    </label>
                                    <p class="mt-1 text-xs text-stone-500">
                                        ডিসপ্যাচ: {{ $item->dispatchedWeightLabel() }} ·
                                        আগে ফেরত: {{ $item->returnedWeightLabel() }} ·
                                        বাকি: {{ $item->remainingWeightLabel() }}
                                    </p>
                                    @if ($item->remainingReturnableUnits() > 0)
                                        <input id="return-{{ $item->id }}"
                                               name="returns[{{ $item->id }}]"
                                               type="number" step="0.0001" min="0.0001"
                                               max="{{ \App\Support\Kilograms::format($item->remainingReturnableUnits()) }}"
                                               placeholder="ফেরতের পরিমাণ (কেজি)"
                                               class="mt-2 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                                    @else
                                        <p class="mt-2 text-xs font-semibold text-emerald-700">সম্পূর্ণ পরিমাণ ইতিমধ্যে ফেরত হয়েছে।</p>
                                    @endif
                                </div>
                            @endforeach
                            <textarea name="reason" rows="2" maxlength="500" placeholder="ফেরতের কারণ / যাচাইকরণের নোট (ঐচ্ছিক)"
                                      class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm"></textarea>
                            <input name="operation_key" required maxlength="80" placeholder="রেফারেন্স (যেমন return-{{ $order->id }}-1)"
                                   value="return-{{ $order->id }}-{{ now()->format('YmdHis') }}-{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4)) }}"
                                   class="w-full rounded-xl border border-stone-300 px-3 py-2 text-sm">
                            <button class="w-full rounded-full border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">স্টকে ফেরত যোগ করুন</button>
                        </form>
                        <p class="mt-3 text-xs leading-5 text-stone-500">
                            শুধুমাত্র যাচাইকৃত ও অনুমোদিত ফিজিক্যাল ফেরতের পর ব্যবহার করুন। প্রতি আইটেমে ডিসপ্যাচ করা পরিমাণের বেশি ফেরত নেওয়া যাবে না; একই operation key দুইবার স্টক বাড়াবে না।
                        </p>
                    @else
                        <p class="mt-3 text-xs leading-5 text-stone-500">ফেরত রেকর্ড করতে অর্ডারটি ডিসপ্যাচ করা থাকতে হবে এবং আপনার inventory অনুমতি থাকতে হবে।</p>
                    @endcan
                </section>

                <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-stone-900">ফেরতের ইতিহাস</h2>
                    @forelse ($order->returnBatches as $batch)
                        <div class="mt-3 rounded-xl bg-stone-50 p-3 text-xs">
                            <p class="font-mono font-semibold text-stone-900">{{ $batch->reference }}</p>
                            <p class="mt-1 text-stone-500">
                                {{ $batch->approved_at?->format('d M Y, h:i A') }}
                                @if ($batch->actor) · {{ $batch->actor->name }} @endif
                            </p>
                            <ul class="mt-2 space-y-1">
                                @foreach ($batch->returns as $return)
                                    <li class="flex justify-between gap-2">
                                        <span>{{ $return->orderItem?->product_title }} — {{ $return->orderItem?->variant_label }}</span>
                                        <span class="font-semibold">{{ \App\Support\Kilograms::formatKg(\App\Support\Kilograms::toUnits($return->quantity_kg)) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($batch->reason)
                                <p class="mt-2 text-stone-600">{{ $batch->reason }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="mt-3 text-xs text-stone-500">এখনও কোনো ফেরত রেকর্ড হয়নি।</p>
                    @endforelse
                </section>
            </aside>
        </div>
    </div>
@endsection
