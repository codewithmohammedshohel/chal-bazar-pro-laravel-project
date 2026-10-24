<div class="max-w-md mx-auto bg-white p-8 rounded shadow">
    <h1 class="text-xl font-semibold mb-6">Forgot Password</h1>
    @if(session('status'))
        <p class="text-green-600 mb-4">{{ session('status') }}</p>
    @endif
    <form wire:submit.prevent="send" class="space-y-4">
        <div>
            <label class="block text-sm mb-1">Email</label>
            <input type="email" wire:model.live.debounce.300ms="email" class="w-full border rounded px-3 py-2">
            @error('email') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>
        <button class="w-full bg-blue-600 text-white py-2 rounded">Send Reset Link</button>
    </form>
    <div class="mt-4 text-sm">
        <a href="{{ route('login') }}" class="text-blue-600">Back to login</a>
    </div>
</div>
