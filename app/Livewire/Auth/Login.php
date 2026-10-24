<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower($this->email);
        $throttleKey = $this->throttleKey($email);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.login_throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        // Correct credentials on an inactive account get a precise, actionable
        // message, but still count towards the throttle.
        if ($user !== null && ! $user->isActive() && Hash::check($this->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => $user->isSuspended()
                    ? __('auth.account_suspended')
                    : __('auth.account_blocked'),
            ]);
        }

        if (! Auth::attempt([
            'email' => $email,
            'password' => $this->password,
            'status' => Permissions::STATUS_ACTIVE,
        ], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->reset('password');

        return redirect()->intended(route('dashboard'));
    }

    protected function throttleKey(string $email): string
    {
        return 'login:'.Str::transliterate($email).'|'.request()->ip();
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.app');
    }
}
