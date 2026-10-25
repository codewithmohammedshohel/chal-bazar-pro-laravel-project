@extends('layouts.storefront')

@section('title', 'আমাদের সম্পর্কে | Chal Bazar Pro')
@section('meta_description', 'চাল বাজার প্রো — চাল ও নিত্যপণ্যের ডেমো ক্যাটালগ এবং Laravel migration প্রকল্পের পরিচিতি।')

@section('content')
    <div class="grid gap-8 lg:grid-cols-[1.5fr_1fr]">
        <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:p-9">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-amber-700">About Chal Bazar Pro</p>
            <h1 class="mt-3 text-3xl font-extrabold text-stone-900">আমাদের সম্পর্কে</h1>

            <div class="mt-5 space-y-4 text-sm leading-7 text-stone-600 sm:text-base">
                <p>
                    চাল বাজার প্রো একটি চাল ও নিত্যপণ্যের দোকানের জন্য তৈরি ডেমো ক্যাটালগ। এই Laravel
                    সংস্করণে চাল, সুগন্ধি চাল এবং অন্যান্য মুদি পণ্য ক্যাটাগরি অনুযায়ী সাজানো হয়।
                    প্রতিটি পণ্যের জন্য কেজি, ২৫ কেজি বস্তা ও ৫০ কেজি বস্তার আলাদা দাম দেখানো হয়।
                </p>
                <p>
                    পণ্যের ছবি, বাংলা ও ইংরেজি নাম, মিল ও সরবরাহকারীর তথ্য এবং SEO বিবরণ ক্যাটালগে সংরক্ষণ
                    করা যায়। বর্তমানে এটি একটি ডেমো সংগ্রহ — এখানে দেখানো কোনো দাম বর্তমান বাজারদর নয়।
                </p>
                <p>
                    এই milestone-এ শুধু ক্যাটালগ ও পাবলিক স্টোরফ্রন্ট তৈরি হয়েছে। কার্ট, COD চেকআউট,
                    স্টক রিজার্ভেশন, অর্ডার প্রসেসিং এবং অ্যাডমিন প্যানেল পরবর্তী ধাপে যুক্ত হবে।
                </p>
            </div>

            <div class="mt-7 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">চাল</p>
                    <p class="mt-1 text-sm text-stone-700">মিনিকেট, বাসমতী, চিনিগুঁড়া, পোলাও, ব্রাউন ও আতপের ডেমো তালিকা।</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">নিত্যপণ্য</p>
                    <p class="mt-1 text-sm text-stone-700">তেল, ডাল, চিনি ও মসলার আলাদা ক্যাটালগ।</p>
                </div>
                <div class="rounded-2xl bg-stone-100 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-stone-700">প্যাক</p>
                    <p class="mt-1 text-sm text-stone-700">কেজি, ২৫ কেজি ও ৫০ কেজি — প্রতিটি ইউনিটের নিজস্ব দাম।</p>
                </div>
            </div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                <h2 class="text-base font-bold text-amber-900">গুরুত্বপূর্ণ নোট</h2>
                <ul class="mt-3 space-y-2 text-sm leading-6 text-amber-900">
                    <li>• সব দাম ডেমো; বাজারদর হিসেবে ব্যবহার করা যাবে না।</li>
                    <li>• ছবিগুলো লোকাল placeholder, বাস্তব পণ্যের ছবি নয়।</li>
                    <li>• এই সাইট থেকে এখন কোনো অর্ডার নেওয়া হয় না।</li>
                </ul>
            </section>

            <section class="rounded-3xl border border-stone-200 bg-white p-6">
                <h2 class="text-base font-bold text-stone-900">ক্যাটালগ ঘুরে দেখুন</h2>
                <div class="mt-3 flex flex-col gap-2 text-sm">
                    <a href="{{ route('home') }}" class="rounded-xl border border-stone-200 px-4 py-2 font-medium text-stone-700 hover:border-amber-400 hover:text-amber-800">
                        চালের সংগ্রহ
                    </a>
                    <a href="{{ route('storefront.other-products') }}" class="rounded-xl border border-stone-200 px-4 py-2 font-medium text-stone-700 hover:border-emerald-400 hover:text-emerald-800">
                        নিত্যপণ্য
                    </a>
                </div>
            </section>
        </aside>
    </div>
@endsection
