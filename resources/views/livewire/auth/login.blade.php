<div class="max-w-md mx-auto bg-white p-8 rounded shadow">
    <h1 class="text-xl font-semibold mb-6">Login</h1>
    <form wire:submit.prevent="login" class="space-y-4">
        <div>
            <label class="block text-sm mb-1">Email</label>
            <input type="email" wire:model.live.debounce.300ms="email" class="w-full border rounded px-3 py-2">
            @error('email') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">Password</label>
            <input type="password" wire:model.live.debounce.300ms="password" class="w-full border rounded px-3 py-2">
            @error('password') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="remember">
            <span class="text-sm">Remember me</span>
        </label>
        <button class="w-full bg-blue-600 text-white py-2 rounded">Login</button>
    </form>
    <div class="mt-4 text-sm flex justify-between">
        <a href="{{ route('register') }}" class="text-blue-600">Register</a>
        <a href="{{ route('password.request') }}" class="text-blue-600">Forgot password?</a>
    </div>
</div>
