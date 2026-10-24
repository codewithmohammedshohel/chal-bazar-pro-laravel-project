<div class="max-w-md mx-auto bg-white p-8 rounded shadow">
    <h1 class="text-xl font-semibold mb-6">Reset Password</h1>

    <form wire:submit="performReset" class="space-y-4">
        <input type="hidden" wire:model="token">

        <div>
            <label for="reset-email" class="block text-sm mb-1">Email</label>
            <input id="reset-email" type="email" autocomplete="email" wire:model="email"
                   class="w-full border rounded px-3 py-2" aria-describedby="reset-email-error">
            @error('email')
                <p id="reset-email-error" class="text-red-600 text-sm" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="reset-password" class="block text-sm mb-1">New Password</label>
            <input id="reset-password" type="password" autocomplete="new-password" wire:model="password"
                   class="w-full border rounded px-3 py-2" aria-describedby="reset-password-error">
            @error('password')
                <p id="reset-password-error" class="text-red-600 text-sm" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="reset-password-confirmation" class="block text-sm mb-1">Confirm Password</label>
            <input id="reset-password-confirmation" type="password" autocomplete="new-password"
                   wire:model="password_confirmation" class="w-full border rounded px-3 py-2">
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                wire:loading.attr="disabled">
            Reset Password
        </button>
    </form>
</div>
