<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $__env->yieldContent('title', 'চাল বাজার প্রো | Chal Bazar Pro') }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($__env->yieldContent('meta_description', 'চাল, সুগন্ধি চাল ও নিত্যপণ্যের ডেমো ক্যাটালগ।')), 160) }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Chal Bazar Pro">
    <meta property="og:title" content="{{ $__env->yieldContent('title', 'চাল বাজার প্রো | Chal Bazar Pro') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-amber-700 focus:px-4 focus:py-2 focus:text-white">
        মূল কনটেন্টে যান
    </a>

    <header class="sticky top-0 z-30 border-b border-amber-100 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-5 gap-y-3 px-4 py-3">
            <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="Chal Bazar Pro — হোম">
                <span class="grid h-10 w-10 place-items-center rounded-full bg-amber-600 text-lg font-bold text-white">চাল</span>
                <span class="leading-tight">
                    <span class="block text-base font-bold tracking-tight text-stone-900">চাল বাজার প্রো</span>
                    <span class="block text-[11px] uppercase tracking-[0.18em] text-amber-700">Chal Bazar Pro</span>
                </span>
            </a>

            <nav aria-label="প্রধান মেনু" class="order-3 flex w-full flex-wrap items-center gap-1 text-sm sm:order-none sm:w-auto">
                <a href="{{ route('home') }}"
                   class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('home') ? 'bg-amber-100 text-amber-900' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' }}">
                    চাল
                </a>
                <a href="{{ route('storefront.other-products') }}"
                   class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('storefront.other-products') ? 'bg-amber-100 text-amber-900' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' }}">
                    নিত্যপণ্য
                </a>
                <a href="{{ route('storefront.about') }}"
                   class="rounded-full px-3 py-1.5 font-medium {{ request()->routeIs('storefront.about') ? 'bg-amber-100 text-amber-900' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900' }}">
                    আমাদের সম্পর্কে
                </a>
            </nav>

            <form method="GET" action="{{ route('home') }}" role="search"
                  class="order-4 flex w-full items-center gap-2 sm:order-none sm:ml-auto sm:w-auto">
                <label for="site-search" class="sr-only">পণ্য খুঁজুন</label>
                <input id="site-search" type="search" name="q" value="{{ request('q') }}"
                       placeholder="চাল, ডাল, তেল খুঁজুন…"
                       class="w-full rounded-full border border-stone-300 bg-stone-50 px-4 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 sm:w-56">
                <button type="submit"
                        class="rounded-full bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 focus:outline-none focus:ring-2 focus:ring-stone-400">
                    খুঁজুন
                </button>
            </form>

            <div class="order-5 flex items-center gap-3 text-sm sm:order-none">
                @auth
                    <a href="{{ route('cart.index') }}" class="relative font-medium text-stone-700 hover:text-amber-700">
                        কার্ট
                        @php($cartCount = app(\App\Services\CartService::class)->count())
                        @if ($cartCount > 0)
                            <span class="absolute -right-4 -top-2 rounded-full bg-amber-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $cartCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('orders.index') }}" class="font-medium text-stone-700 hover:text-amber-700">অর্ডার</a>
                    <a href="{{ route('dashboard') }}" class="font-medium text-amber-800 hover:text-amber-600">ড্যাশবোর্ড</a>
                    <a href="{{ route('logout') }}" class="text-stone-600 hover:text-stone-900">লগআউট</a>
                @else
                    <a href="{{ route('login') }}" class="font-medium text-amber-800 hover:text-amber-600">লগইন</a>
                    <a href="{{ route('register') }}" class="text-stone-600 hover:text-stone-900">রেজিস্টার</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main-content" class="mx-auto w-full max-w-7xl px-4 py-8 sm:py-10">
        @yield('content')
    </main>

    <footer class="mt-12 border-t border-stone-200 bg-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <p class="text-base font-bold text-stone-900">চাল বাজার প্রো</p>
                <p class="mt-2 max-w-sm text-sm leading-6 text-stone-600">
                    চাল ও নিত্যপণ্যের ডেমো ক্যাটালগ। এই সাইটের সব দাম শুধু ডেমো উদ্দেশ্যে দেখানো হয়েছে,
                    বর্তমান বাজারদর নয়।
                </p>
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">ক্যাটালগ</p>
                <ul class="mt-3 space-y-2 text-sm text-stone-600">
                    <li><a class="hover:text-amber-700" href="{{ route('home') }}">চাল</a></li>
                    <li><a class="hover:text-amber-700" href="{{ route('storefront.other-products') }}">নিত্যপণ্য</a></li>
                    <li><a class="hover:text-amber-700" href="{{ route('storefront.about') }}">আমাদের সম্পর্কে</a></li>
                </ul>
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">অর্ডার</p>
                <p class="mt-3 text-sm leading-6 text-stone-600">
                    ক্যাশ অন ডেলিভারি (COD) চেকআউট চালু আছে। দোকান থেকে ১০ কিমি পর্যন্ত ডেলিভারি ফ্রি।
                    ডেমো ক্যাটালগ — সব দাম শুধু ডেমো উদ্দেশ্যে।
                </p>
            </div>
        </div>
        <div class="border-t border-stone-100 py-4 text-center text-xs text-stone-500">
            © {{ date('Y') }} Chal Bazar Pro — Laravel + Blade migration. Demo catalog only.
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
