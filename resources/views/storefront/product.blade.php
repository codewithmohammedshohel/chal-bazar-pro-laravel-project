@extends('layouts.storefront')

@section('title', $title)
@section('meta_description', $metaDescription)

@section('content')
    <nav aria-label="Breadcrumb" class="text-sm text-stone-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('home') }}" class="hover:text-amber-700">হোম</a></li>
            <li aria-hidden="true">/</li>
            @if ($product->category)
                <li>
                    <a href="{{ route('home', ['category' => $product->category->slug]) }}" class="hover:text-amber-700">
                        {{ $product->category->displayName() }}
                    </a>
                </li>
                <li aria-hidden="true">/</li>
            @endif
            <li aria-current="page" class="font-medium text-stone-800">{{ $product->displayTitle() }}</li>
        </ol>
    </nav>

    @php
        $images = $product->media->where('kind', \App\Models\ProductMedia::KIND_IMAGE);
        $videos = $product->media->where('kind', \App\Models\ProductMedia::KIND_VIDEO);
        $variants = $product->variants->where('is_active', true);
        $defaultVariant = $variants->first();
    @endphp

    <div class="mt-6 grid gap-8 lg:grid-cols-2">
        <section aria-label="পণ্যের ছবি ও ভিডিও" class="space-y-3">
            @if ($images->isNotEmpty())
                <img src="{{ $images->first()->url }}"
                     alt="{{ $images->first()->altText($product->title) }}"
                     class="aspect-[4/3] w-full rounded-3xl border border-stone-200 bg-stone-100 object-cover shadow-sm">

                @if ($images->count() > 1)
                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($images->skip(1) as $image)
                            <img src="{{ $image->url }}"
                                 alt="{{ $image->altText($product->title) }}"
                                 loading="lazy"
                                 class="aspect-square w-full rounded-2xl border border-stone-200 bg-stone-100 object-cover">
                        @endforeach
                    </div>
                @endif
            @else
                <div class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-3 rounded-3xl border border-dashed border-stone-300 bg-white text-stone-400">
                    <span class="text-5xl" aria-hidden="true">🌾</span>
                    <p class="text-sm font-medium">এই পণ্যের ছবি এখনো যোগ করা হয়নি</p>
                </div>
            @endif

            @foreach ($videos as $video)
                <video controls preload="metadata" class="w-full rounded-2xl border border-stone-200 bg-black">
                    <source src="{{ $video->url }}">
                    আপনার ব্রাউজার ভিডিও চালাতে পারে না।
                </video>
            @endforeach
        </section>

        <section class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
            @if ($product->category)
                <a href="{{ route('home', ['category' => $product->category->slug]) }}"
                   class="inline-block rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800 hover:bg-amber-200">
                    {{ $product->category->displayName() }}
                </a>
            @endif

            <h1 class="mt-3 text-2xl font-extrabold leading-tight text-stone-900 sm:text-3xl">
                {{ $product->displayTitle() }}
            </h1>

            @if ($product->secondaryTitle())
                <p class="mt-1 text-base text-stone-500">{{ $product->secondaryTitle() }}</p>
            @endif

            @if ($product->description)
                <p class="mt-4 text-sm leading-7 text-stone-600">{!! nl2br(e($product->description)) !!}</p>
            @endif

            @if ($variants->isNotEmpty())
                <form method="POST" action="{{ route('cart.add-item') }}" class="mt-6 space-y-3 rounded-2xl border border-stone-200 bg-white p-4">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_120px]">
                        <div>
                            <label for="variant-select" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">ইউনিট</label>
                            <select id="variant-select" name="product_variant_id"
                                    class="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
                                @foreach ($variants as $variant)
                                    <option value="{{ $variant->id }}">
                                        {{ $variant->displayLabel() }}@if ($variant->secondaryLabel()) · {{ $variant->secondaryLabel() }}@endif · {{ $variant->weightLabel() }} — ৳ {{ number_format((float) $variant->retail_price, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="variant-qty" class="block text-xs font-semibold uppercase tracking-wide text-stone-500">পরিমাণ</label>
                            <input id="variant-qty" type="number" name="quantity" value="1" min="1" max="100"
                                   class="mt-1 w-full rounded-xl border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200">
                        </div>
                    </div>

                    @guest
                        <p class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            অর্ডার করতে <a href="{{ route('login') }}" class="font-semibold underline">লগইন</a> করুন।
                        </p>
                    @endguest

                    <button type="submit" @guest disabled @endguest
                            class="w-full rounded-full bg-amber-600 px-6 py-3 text-sm font-bold text-white hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-300 disabled:cursor-not-allowed disabled:opacity-50">
                        কার্টে যোগ করুন
                    </button>
                </form>
            @else
                <p class="mt-6 rounded-2xl bg-stone-100 p-4 text-sm font-medium text-stone-600">
                    এই পণ্যের দাম শীঘ্রই যোগ করা হবে।
                </p>
            @endif

            @if ($product->type === \App\Models\Product::TYPE_RICE && ($product->mill_name || $product->supplier || $product->grain_type || $product->processing_method))
                <dl class="mt-6 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    @if ($product->mill_name)
                        <div class="rounded-xl bg-stone-50 p-3">
                            <dt class="text-xs uppercase tracking-wide text-stone-500">মিল</dt>
                            <dd class="mt-1 font-medium text-stone-800">{{ $product->mill_name }}</dd>
                        </div>
                    @endif
                    @if ($product->supplier)
                        <div class="rounded-xl bg-stone-50 p-3">
                            <dt class="text-xs uppercase tracking-wide text-stone-500">সরবরাহকারী</dt>
                            <dd class="mt-1 font-medium text-stone-800">{{ $product->supplier }}</dd>
                        </div>
                    @endif
                    @if ($product->grain_type)
                        <div class="rounded-xl bg-stone-50 p-3">
                            <dt class="text-xs uppercase tracking-wide text-stone-500">দানার ধরন</dt>
                            <dd class="mt-1 font-medium text-stone-800">{{ $product->grain_type }}</dd>
                        </div>
                    @endif
                    @if ($product->processing_method)
                        <div class="rounded-xl bg-stone-50 p-3">
                            <dt class="text-xs uppercase tracking-wide text-stone-500">প্রসেসিং</dt>
                            <dd class="mt-1 font-medium text-stone-800">{{ $product->processing_method }}</dd>
                        </div>
                    @endif
                </dl>
            @endif

            <p class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                ক্যাশ অন ডেলিভারি (COD)। দোকান থেকে ১০ কিমি পর্যন্ত ডেলিভারি ফ্রি, এর বেশি হলে প্রতি অতিরিক্ত কিমিতে ১৫৳।
                সব দাম ডেমো — বর্তমান বাজারদর নয়।
            </p>
        </section>
    </div>

    @if ($related->isNotEmpty())
        <section aria-labelledby="related-heading" class="mt-12">
            <h2 id="related-heading" class="text-xl font-bold text-stone-900 sm:text-2xl">একই ক্যাটাগরির আরও পণ্য</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($related as $relatedProduct)
                    <x-storefront.product-card :product="$relatedProduct" />
                @endforeach
            </div>
        </section>
    @endif
@endsection
