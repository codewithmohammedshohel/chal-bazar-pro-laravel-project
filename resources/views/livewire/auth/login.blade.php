<div class="max-w-md mx-auto bg-white p-8 rounded shadow">
    <h1 class="text-xl font-semibold mb-6">Login</h1>

    @if (session('status'))
        <p class="mb-4 rounded bg-green-50 p-3 text-sm text-green-700" role="status">{{ session('status') }}</p>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="login-email" class="block text-sm mb-1">Email</label>
            <input id="login-email" type="email" autocomplete="email" wire:model="email"
                   class="w-full border rounded px-3 py-2" aria-describedby="login-email-error">
            @error('email')
                <p id="login-email-error" class="text-red-600 text-sm" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="login-password" class="block text-sm mb-1">Password</label>
            <input id="login-password" type="password" autocomplete="current-password" wire:model="password"
                   class="w-full border rounded px-3 py-2" aria-describedby="login-password-error">
            @error('password')
                <p id="login-password-error" class="text-red-600 text-sm" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="remember">
            <span class="text-sm">Remember me</span>
        </label>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                wire:loading.attr="disabled">
            Login
        </button>
    </form>

    <div class="mt-4 text-sm flex justify-between">
        <a href="{{ route('register') }}" class="text-blue-600">Register</a>
        <a href="{{ route('password.request') }}" class="text-blue-600">Forgot password?</a>
    </div>
</div>
