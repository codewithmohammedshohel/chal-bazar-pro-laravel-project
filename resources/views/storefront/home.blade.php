@extends('layouts.storefront')

@section('title', $pageTitle)
@section('meta_description', $metaDescription)

@section('content')
    <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-amber-700 via-amber-600 to-orange-500 px-6 py-10 text-white shadow-lg sm:px-10 sm:py-14">
        <div class="grid items-center gap-8 lg:grid-cols-[1.4fr_1fr]">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-amber-100">Chal Bazar Pro · Demo Catalog</p>
                <h1 class="mt-3 text-3xl font-extrabold leading-tight sm:text-4xl lg:text-5xl">
                    ভালো চাল,<br class="hidden sm:block"> সহজে খুঁজে নিন।
                </h1>
                <p class="mt-4 max-w-xl text-sm leading-7 text-amber-50 sm:text-base">
                    মিনিকেট, বাসমতী, চিনিগুঁড়া ও পোলাও চালের ডেমো সংগ্রহ। কেজি ও বস্তা অনুযায়ী দাম দেখুন,
                    ক্যাটাগরি দিয়ে ফিল্টার করুন। এই দামগুলো শুধু ডেমো — বাজারদর নয়।
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="#catalog"
                       class="rounded-full bg-white px-5 py-2.5 text-sm font-bold text-amber-800 shadow hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-white">
                        চাল দেখুন
                    </a>
                    <a href="{{ route('storefront.other-products') }}"
                       class="rounded-full border border-white/60 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white">
                        নিত্যপণ্য
                    </a>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-center">
                <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
                    <dt class="text-xs uppercase tracking-wide text-amber-100">চালের ধরন</dt>
                    <dd class="mt-1 text-2xl font-bold">৬+</dd>
                </div>
                <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
                    <dt class="text-xs uppercase tracking-wide text-amber-100">প্যাক সাইজ</dt>
                    <dd class="mt-1 text-2xl font-bold">৩</dd>
                </div>
                <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
                    <dt class="text-xs uppercase tracking-wide text-amber-100">ক্যাটাগরি</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ $categories->count() }}</dd>
                </div>
                <div class="rounded-2xl bg-white/10 p-4 backdrop-blur">
                    <dt class="text-xs uppercase tracking-wide text-amber-100">মোট পণ্য</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ $products->total() }}</dd>
                </div>
            </dl>
        </div>
    </section>

    @if ($featured->isNotEmpty() && $search === '' && $categorySlug === '')
        <section aria-labelledby="featured-heading" class="mt-10">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Featured</p>
                    <h2 id="featured-heading" class="mt-1 text-xl font-bold text-stone-900 sm:text-2xl">নির্বাচিত চাল</h2>
                </div>
                <a href="{{ route('storefront.other-products') }}" class="text-sm font-medium text-amber-700 hover:text-amber-900">
                    নিত্যপণ্যও দেখুন →
                </a>
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $product)
                    <x-storefront.product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    <div id="catalog">
        @include('storefront.partials.catalog-listing')
    </div>
@endsection
