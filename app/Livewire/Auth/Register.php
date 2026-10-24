<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        // Registration always creates an active customer. Role and status are
        // set from server-side constants, never from submitted values.
        $user = User::create([
            'name' => $this->name,
            'email' => Str::lower($this->email),
            'password' => Hash::make($this->password),
            'role' => Permissions::ROLE_CUSTOMER,
            'status' => Permissions::STATUS_ACTIVE,
        ]);

        Auth::login($user);
        session()->regenerate();

        $this->reset('password', 'password_confirmation');

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.register')->layout('layouts.app');
    }
}
