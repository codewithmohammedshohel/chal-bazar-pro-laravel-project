<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public function send()
    {
        $this->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower($this->email);
        $throttleKey = 'password-email:'.Str::transliterate($email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            throw ValidationException::withMessages([
                'email' => __('auth.reset_throttle'),
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        // The response is intentionally generic so it never reveals whether an
        // account exists for the submitted address.
        Password::sendResetLink(['email' => $email]);

        session()->flash('status', __('passwords.sent'));

        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.auth.forgot-password')->layout('layouts.app');
    }
}
