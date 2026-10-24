<div class="max-w-md mx-auto bg-white p-8 rounded shadow">
    <h1 class="text-xl font-semibold mb-6">Reset Password</h1>
    <form wire:submit.prevent="performReset" class="space-y-4">
        <input type="hidden" wire:model="token">
        <div>
            <label class="block text-sm mb-1">Email</label>
            <input type="email" wire:model.live.debounce.300ms="email" class="w-full border rounded px-3 py-2">
            @error('email') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm mb-1">New Password</label>
            <input type="password" wire:model.live.debounce.300ms="password" class="w-full border rounded px-3 py-2">
            @error('password') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div        <div>
            <label class="block text-sm mb-1">Confirm Password</label>
            <input type="password" wire:model.live.debounce.300ms="password_confirmation" class="w-full border rounded px-3 py-2">
        </div>
        <button class="w-full bg-blue-600 text-white py-2 rounded">Reset Password</button>
    </form>
</div>
