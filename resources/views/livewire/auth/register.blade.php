<div class="max-w-md mx-auto bg-white p-8 rounded shadow">
    <h1 class="text-xl font-semibold mb-6">Register</h1>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label for="register-name" class="block text-sm mb-1">Name</label>
            <input id="register-name" type="text" autocomplete="name" wire:model="name"
                   class="w-full border rounded px-3 py-2" aria-describedby="register-name-error">
            @error('name')
                <p id="register-name-error" class="text-red-600 text-sm" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="register-email" class="block text-sm mb-1">Email</label>
            <input id="register-email" type="email" autocomplete="email" wire:model="email"
                   class="w-full border rounded px-3 py-2" aria-describedby="register-email-error">
            @error('email')
                <p id="register-email-error" class="text-red-600 text-sm" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="register-password" class="block text-sm mb-1">Password</label>
            <input id="register-password" type="password" autocomplete="new-password" wire:model="password"
                   class="w-full border rounded px-3 py-2" aria-describedby="register-password-error">
            @error('password')
                <p id="register-password-error" class="text-red-600 text-sm" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="register-password-confirmation" class="block text-sm mb-1">Confirm Password</label>
            <input id="register-password-confirmation" type="password" autocomplete="new-password"
                   wire:model="password_confirmation" class="w-full border rounded px-3 py-2">
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                wire:loading.attr="disabled">
            Register
        </button>
    </form>

    <div class="mt-4 text-sm">
        <a href="{{ route('login') }}" class="text-blue-600">Already have an account? Login</a>
    </div>
</div>
