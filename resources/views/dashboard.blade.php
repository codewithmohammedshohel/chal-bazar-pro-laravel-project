<x-layouts.app>
    <div class="bg-white p-6 rounded shadow">
        <h1 class="text-xl font-semibold">Dashboard</h1>
        <p class="mt-2 text-gray-600">Welcome, {{ auth()->user()->name }}</p>
    </div>
</x-layouts.app>
