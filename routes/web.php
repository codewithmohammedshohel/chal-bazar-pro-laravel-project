<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Logout;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use Illuminate\Support\Facades\Route;

/*
 * Public storefront
 */
Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/other-products', [CatalogController::class, 'otherProducts'])->name('storefront.other-products');
Route::get('/about', [CatalogController::class, 'about'])->name('storefront.about');
Route::get('/products/{product}', [CatalogController::class, 'show'])->name('storefront.product');

/*
 * Authentication
 */
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/logout', Logout::class)->name('logout');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
