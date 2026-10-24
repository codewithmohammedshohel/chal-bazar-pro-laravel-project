<div class="max-w-md mx-auto bg-white p-8 rounded shadow">
    <h1 class="text-xl font-semibold mb-6">Register</h1>
    <form wire:submit.prevent="register" class="space-y-4">
        <div>
            <label class="block text-sm mb-1">Name</label>
            <input type="text" wire:model.live.debounce.300ms="name" class="w-full border rounded px-3 py-2">
            @error('name') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>
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
        <div>
            <label class="block text-sm mb-1">Confirm Password</label>
            <input type="password" wire:model.live.debounce.300ms="password_confirmation" class="w-full border rounded px-3 py-2">
        </div>
        <button class="w-full bg-blue-600 text-white py-2 rounded">Register</button>
    </form>
    <div class="mt-4 text-sm">
        <a href="{{ route('login') }}" class="text-blue-600">Already have account? Login</a>
    </div>
</div>
