<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount($token = null)
    {
        $this->token = $token ?? request()->route('token');
    }

    public function performReset()
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        $email = Str::lower($this->email);
        $throttleKey = 'password-reset:'.Str::transliterate($email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.reset_throttle'),
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $status = Password::reset(
            [
                'email' => $email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            RateLimiter::clear($throttleKey);
            $this->reset('password', 'password_confirmation');
            session()->flash('status', __($status));

            return redirect()->route('login');
        }

        $this->addError('email', __($status));
    }

    public function render()
    {
        return view('livewire.auth.reset-password')->layout('layouts.app');
    }
}
