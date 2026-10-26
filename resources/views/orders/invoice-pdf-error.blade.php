@extends('layouts.storefront')

@section('title', 'ইনভয়েস তৈরি করা যায়নি | Chal Bazar Pro')

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="rounded-3xl border border-amber-200 bg-amber-50 p-8">
            <h1 class="text-xl font-extrabold text-stone-900">PDF ইনভয়েস তৈরি করা যায়নি</h1>
            <p class="mt-3 text-sm leading-6 text-stone-700">
                আপনার অর্ডার ও ইনভয়েসের তথ্য সংরক্ষিত আছে। শুধু PDF ফাইলটি তৈরি করা যায়নি।
                অনুগ্রহ করে কিছুক্ষণ পর আবার চেষ্টা করুন, অথবা প্রিন্টযোগ্য HTML ইনভয়েস ব্যবহার করুন।
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('orders.invoice', $order) }}"
                   class="rounded-full bg-stone-900 px-5 py-2 text-sm font-semibold text-white hover:bg-stone-700">
                    HTML ইনভয়েস দেখুন
                </a>
                <a href="{{ route('orders.show', $order) }}"
                   class="rounded-full border border-stone-300 px-5 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100">
                    অর্ডারে ফিরে যান
                </a>
            </div>
        </div>
    </div>
@endsection
