@props(['product'])

@php
    $image = $product->primaryImage();
    $price = $product->lowestRetailPrice();
    $category = $product->category;
@endphp

<article class="group flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
    <a href="{{ route('storefront.product', $product) }}" class="block aspect-[4/3] overflow-hidden bg-stone-100">
        @if ($image && $image->isImage())
            <img src="{{ $image->url }}" alt="{{ $image->altText($product->title) }}"
                 loading="lazy" decoding="async"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <span class="flex h-full w-full flex-col items-center justify-center gap-2 text-stone-400">
                <span class="grid h-14 w-14 place-items-center rounded-full bg-stone-200 text-2xl">🌾</span>
                <span class="text-xs font-medium uppercase tracking-wide">ছবি নেই</span>
            </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-4">
        @if ($category)
            <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-700">
                {{ $category->displayName() }}
            </p>
        @endif

        <h3 class="mt-1 text-base font-semibold leading-snug text-stone-900">
            <a href="{{ route('storefront.product', $product) }}" class="hover:text-amber-700">
                {{ $product->displayTitle() }}
            </a>
        </h3>

        @if ($product->secondaryTitle())
            <p class="mt-0.5 text-sm text-stone-500">{{ $product->secondaryTitle() }}</p>
        @endif

        @if ($product->grain_type)
            <p class="mt-2 text-xs text-stone-500">দানা: {{ $product->grain_type }}</p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-3 pt-4">
            <div>
                @if ($price !== null)
                    <p class="text-[11px] uppercase tracking-wide text-stone-500">শুরু</p>
                    <p class="text-lg font-bold text-stone-900">৳ {{ number_format($price, 2) }}</p>
                @else
                    <p class="text-sm font-medium text-stone-500">দাম শীঘ্রই আসছে</p>
                @endif
            </div>
            <a href="{{ route('storefront.product', $product) }}"
               class="rounded-full border border-amber-600 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-300">
                বিস্তারিত
            </a>
        </div>
    </div>
</article>
