@extends('layouts.storefront')

@section('title', $pageTitle)
@section('meta_description', $metaDescription)

@section('content')
    <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-800 via-emerald-700 to-lime-600 px-6 py-10 text-white shadow-lg sm:px-10 sm:py-12">
        <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-emerald-100">Grocery &amp; Essentials</p>
            <h1 class="mt-3 text-3xl font-extrabold leading-tight sm:text-4xl">নিত্যপণ্য ও মুদি সামগ্রী</h1>
            <p class="mt-3 text-sm leading-7 text-emerald-50 sm:text-base">
                তেল, ডাল, চিনি ও মসলার ডেমো ক্যাটালগ। ক্যাটাগরি বেছে নিন, নাম দিয়ে খুঁজুন এবং প্রতিটি
                প্যাকের দাম দেখুন। দামগুলো শুধু ডেমো উদ্দেশ্যে।
            </p>
            <a href="{{ route('home') }}"
               class="mt-6 inline-block rounded-full bg-white px-5 py-2.5 text-sm font-bold text-emerald-800 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-white">
                চালের সংগ্রহে যান
            </a>
        </div>
    </section>

    @include('storefront.partials.catalog-listing')
@endsection
