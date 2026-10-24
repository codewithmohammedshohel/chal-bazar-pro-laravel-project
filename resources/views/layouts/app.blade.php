<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Chal Bazar Pro') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased bg-gray-50 text-gray-900">
    <div class="min-h-screen flex flex-col">
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between">
                <a href="/" class="font-semibold">{{ config('app.name', 'Chal Bazar Pro') }}</a>
                <nav class="flex gap-4">
                    @auth
                        <span>{{ auth()->user()->name }}</span>
                        <a href="{{ route('logout') }}">Logout</a>
                    @else
                        <a href="{{ route('login') }}">Login</a>
                        <a href="{{ route('register') }}">Register</a>
                    @endauth
                </nav>
            </div>
        </header>
        <main class="flex-1 max-w-7xl mx-auto w-full px-4 py-8">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
