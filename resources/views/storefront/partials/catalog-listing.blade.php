@php
    $resetUrl = url()->current();
@endphp

<section aria-labelledby="catalog-heading" class="mt-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 id="catalog-heading" class="text-xl font-bold text-stone-900 sm:text-2xl">
                {{ $type === \App\Models\Product::TYPE_RICE ? 'সব চাল' : 'সব নিত্যপণ্য' }}
            </h2>
            <p class="mt-1 text-sm text-stone-500">
                {{ $products->total() }} টি পণ্য
                @if ($activeCategory)
                    · {{ $activeCategory->displayName() }}
                @endif
                @if ($search !== '')
                    · “{{ $search }}” এর ফলাফল
                @endif
            </p>
        </div>

        <form method="GET" action="{{ $resetUrl }}" role="search" class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <label for="catalog-search" class="sr-only">ক্যাটালগে খুঁজুন</label>
            <input id="catalog-search" type="search" name="q" value="{{ $search }}"
                   placeholder="নাম দিয়ে খুঁজুন…"
                   class="w-full rounded-full border border-stone-300 bg-white px-4 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 sm:w-56">
            @if ($categorySlug !== '')
                <input type="hidden" name="category" value="{{ $categorySlug }}">
            @endif
            <button type="submit"
                    class="rounded-full bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-300">
                খুঁজুন
            </button>
            @if ($search !== '' || $categorySlug !== '')
                <a href="{{ $resetUrl }}" class="text-sm font-medium text-stone-600 underline hover:text-stone-900">রিসেট</a>
            @endif
        </form>
    </div>

    @if ($categories->isNotEmpty())
        <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ $resetUrl }}{{ $search !== '' ? '?q='.urlencode($search) : '' }}"
               aria-current="{{ $categorySlug === '' ? 'page' : 'false' }}"
               class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $categorySlug === '' ? 'border-amber-600 bg-amber-600 text-white' : 'border-stone-300 bg-white text-stone-700 hover:border-amber-400' }}">
                সব
            </a>
            @foreach ($categories as $category)
                <a href="{{ $resetUrl.'?'.http_build_query(array_filter(['q' => $search, 'category' => $category->slug])) }}"
                   aria-current="{{ $categorySlug === $category->slug ? 'page' : 'false' }}"
                   class="rounded-full border px-3 py-1.5 text-sm font-medium {{ $categorySlug === $category->slug ? 'border-amber-600 bg-amber-600 text-white' : 'border-stone-300 bg-white text-stone-700 hover:border-amber-400' }}">
                    {{ $category->displayName() }}
                    <span class="ml-1 text-xs opacity-70">{{ $category->product_count }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($products->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center">
            <p class="text-3xl" aria-hidden="true">🌾</p>
            <h3 class="mt-3 text-lg font-semibold text-stone-900">কোনো পণ্য পাওয়া যায়নি</h3>
            <p class="mt-1 text-sm text-stone-500">অন্য নামে খুঁজুন অথবা ফিল্টার রিসেট করুন।</p>
            <a href="{{ $resetUrl }}" class="mt-4 inline-block rounded-full bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                সব পণ্য দেখুন
            </a>
        </div>
    @else
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($products as $product)
                <x-storefront.product-card :product="$product" />
            @endforeach
        </div>

        @if ($products->hasPages())
            <div class="mt-8">
                {{ $products->links() }}
            </div>
        @endif
    @endif
</section>
