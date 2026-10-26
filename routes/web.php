<?php

use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogAdminController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
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

    /*
     * Customer cart and checkout (COD only).
     */
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.add-item');
    Route::post('/cart/items/{variant}', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/items/{variant}/update', [CartController::class, 'update'])->name('cart.update');
    Route::post('/cart/items/{variant}/remove', [CartController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    /*
     * Customer orders and invoices.
     */
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{order}/invoice', [InvoiceController::class, 'show'])->name('orders.invoice');
    Route::get('/orders/{order}/invoice/pdf', [InvoiceController::class, 'pdf'])->name('orders.invoice.pdf');

    /*
     * Admin order processing.
     */
    Route::get('/admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/admin/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
    Route::post('/admin/orders/{order}/transition', [AdminOrderController::class, 'transition'])->name('admin.orders.transition');
    Route::post('/admin/orders/{order}/return', [AdminOrderController::class, 'recordReturn'])->name('admin.orders.return');

    Route::get('/admin/catalog/categories', [CatalogAdminController::class, 'categories'])->name('admin.catalog.categories');
    Route::get('/admin/catalog/products', [CatalogAdminController::class, 'products'])->name('admin.catalog.products');
    Route::get('/admin/catalog/variants', [CatalogAdminController::class, 'variants'])->name('admin.catalog.variants');
});
