<div class="max-w-md mx-auto bg-white p-8 rounded shadow">
    <h1 class="text-xl font-semibold mb-6">Forgot Password</h1>

    @if (session('status'))
        <p class="mb-4 rounded bg-green-50 p-3 text-sm text-green-700" role="status">{{ session('status') }}</p>
    @endif

    <p class="mb-4 text-sm text-gray-600">
        Enter your email address and we will send a password reset link if an account exists.
    </p>

    <form wire:submit="send" class="space-y-4">
        <div>
            <label for="forgot-email" class="block text-sm mb-1">Email</label>
            <input id="forgot-email" type="email" autocomplete="email" wire:model="email"
                   class="w-full border rounded px-3 py-2" aria-describedby="forgot-email-error">
            @error('email')
                <p id="forgot-email-error" class="text-red-600 text-sm" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                wire:loading.attr="disabled">
            Send Reset Link
        </button>
    </form>

    <div class="mt-4 text-sm">
        <a href="{{ route('login') }}" class="text-blue-600">Back to login</a>
    </div>
</div>
