<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Logout extends Component
{
    public function mount()
    {
        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        return $this->redirectRoute('login', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.logout')->layout('layouts.app');
    }
}
