<?php

namespace App\Providers;

use App\Contracts\InvoicePdfRenderer;
use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Services\Invoices\MpdfInvoicePdfRenderer;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InvoicePdfRenderer::class, MpdfInvoicePdfRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Controlled superadmin bypass: only an active superadmin bypasses the
         * per-permission gates, and only for keys that exist in the catalogue.
         * Suspended/blocked superadmins still receive a denial because
         * hasPermission() rejects inactive accounts.
         */
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isActive() && $user->isSuperadmin() && Permissions::isValid($ability)) {
                return true;
            }

            return null;
        });

        foreach (Permissions::all() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }

        Gate::policy(Order::class, OrderPolicy::class);
    }
}
