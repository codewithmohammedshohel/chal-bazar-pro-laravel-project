@extends('layouts.storefront')

@section('title', 'চেকআউট | Chal Bazar Pro')

@section('content')
    <div class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <section>
            <h1 class="text-2xl font-extrabold text-stone-900 sm:text-3xl">চেকআউট</h1>
            <p class="mt-2 text-sm text-stone-600">ক্যাশ অন ডেলিভারি (COD)। অর্ডার নিশ্চিত করার আগে স্টক ও দাম সার্ভারে যাচাই করা হবে।</p>

            @if ($errors->any())
                <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('checkout.store') }}" class="mt-6 space-y-5 rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                <div>
                    <label for="delivery_address" class="block text-sm font-semibold text-stone-800">
                        ডেলিভারি ঠিকানা <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <textarea id="delivery_address" name="delivery_address" rows="3" required minlength="10" maxlength="1000"
                              class="mt-2 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200"
                              placeholder="বাসা/রোড, এলাকা, শহর">{{ old('delivery_address') }}</textarea>
                    <p class="mt-1 text-xs text-stone-500">অন্তত ১০ অক্ষর। এই ঠিকানাটি অর্ডারের সাথে সংরক্ষিত হবে।</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="delivery_lat" class="block text-sm font-semibold text-stone-800">অক্ষাংশ (latitude) <span class="text-red-600" aria-hidden="true">*</span></label>
                        <input id="delivery_lat" name="delivery_lat" type="number" step="0.0000001" required
                               value="{{ old('delivery_lat') }}"
                               class="mt-2 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
                    </div>
                    <div>
                        <label for="delivery_lng" class="block text-sm font-semibold text-stone-800">দ্রাঘিমাংশ (longitude) <span class="text-red-600" aria-hidden="true">*</span></label>
                        <input id="delivery_lng" name="delivery_lng" type="number" step="0.0000001" required
                               value="{{ old('delivery_lng') }}"
                               class="mt-2 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
                    </div>
                </div>

                <button type="button" id="use-location"
                        class="rounded-full border border-stone-300 px-4 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">
                    আমার বর্তমান লোকেশন ব্যবহার করুন
                </button>

                <div>
                    <label for="customer_note" class="block text-sm font-semibold text-stone-800">অতিরিক্ত নির্দেশনা (ঐচ্ছিক)</label>
                    <textarea id="customer_note" name="customer_note" rows="2" maxlength="500"
                              class="mt-2 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">{{ old('customer_note') }}</textarea>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                    <p class="font-semibold">ডেলিভারি চার্জ নীতি</p>
                    <p class="mt-1">দোকান থেকে ১০ কিমি পর্যন্ত ফ্রি। এর বেশি হলে প্রতি অতিরিক্ত কিমিতে ১৫৳। চূড়ান্ত চার্জ সার্ভারে হিসাব করা হবে।</p>
                    <p class="mt-2 text-xs">দোকানের লোকেশন: {{ $shopLat }}, {{ $shopLng }}</p>
                </div>

                <button type="submit"
                        class="w-full rounded-full bg-amber-600 px-6 py-3 text-sm font-bold text-white hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-300">
                    অর্ডার নিশ্চিত করুন (COD)
                </button>
            </form>
        </section>

        <aside class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-stone-900">অর্ডার সারাংশ</h2>
            <ul class="mt-4 space-y-3 text-sm">
                @foreach ($lines as $line)
                    <li class="flex justify-between gap-3">
                        <span class="text-stone-600">
                            {{ $line['variant']->product->displayTitle() }}
                            <span class="block text-xs text-stone-400">{{ $line['variant']->displayLabel() }} × {{ $line['quantity'] }}</span>
                        </span>
                        <span class="font-semibold text-stone-900">৳ {{ number_format($line['line_total'], 2) }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 border-t border-stone-200 pt-4 text-xs text-stone-500">
                এই সারাংশের দাম ডাটাবেস থেকে নেওয়া। চূড়ান্ত ডেলিভারি চার্জ অর্ডার তৈরি হওয়ার সময় সার্ভারে যাচাই হবে।
            </p>
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('use-location')?.addEventListener('click', function () {
            if (!navigator.geolocation) {
                alert('এই ব্রাউজারে লোকেশন সাপোর্ট নেই।');
                return;
            }

            navigator.geolocation.getCurrentPosition(function (position) {
                document.getElementById('delivery_lat').value = position.coords.latitude.toFixed(7);
                document.getElementById('delivery_lng').value = position.coords.longitude.toFixed(7);
            }, function () {
                alert('লোকেশন পাওয়া যায়নি। ম্যানুয়ালি অক্ষাংশ/দ্রাঘিমাংশ দিন।');
            });
        });
    </script>
@endpush
