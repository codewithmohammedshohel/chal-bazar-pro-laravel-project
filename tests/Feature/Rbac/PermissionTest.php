<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_superadmin_holds_every_catalog_permission(): void
    {
        $user = User::factory()->superadmin()->create();

        foreach (Permissions::all() as $permission) {
            $this->assertTrue($user->hasPermission($permission), "Missing permission: {$permission}");
            $this->assertTrue(Gate::forUser($user)->allows($permission), "Gate denied: {$permission}");
        }
    }

    public function test_admin_staff_and_customer_role_defaults_match_the_legacy_catalog(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        foreach (['dashboard', 'pos', 'inventory', 'orders', 'reports', 'settings', 'seo', 'invoices'] as $permission) {
            $this->assertTrue($admin->hasPermission($permission), "Admin should have {$permission}");
        }

        foreach (['users', 'roles', 'audit_logs', 'customer_orders'] as $permission) {
            $this->assertFalse($admin->hasPermission($permission), "Admin should not have {$permission}");
        }

        foreach (['pos', 'orders', 'attendance', 'payroll'] as $permission) {
            $this->assertTrue($staff->hasPermission($permission), "Staff should have {$permission}");
        }

        foreach (['dashboard', 'reports', 'users', 'inventory'] as $permission) {
            $this->assertFalse($staff->hasPermission($permission), "Staff should not have {$permission}");
        }

        foreach (['dashboard', 'customer_orders', 'customer_rfq', 'customer_ledger', 'customer_profile'] as $permission) {
            $this->assertTrue($customer->hasPermission($permission), "Customer should have {$permission}");
        }

        foreach (['pos', 'orders', 'reports', 'users'] as $permission) {
            $this->assertFalse($customer->hasPermission($permission), "Customer should not have {$permission}");
        }
    }

    public function test_an_explicit_grant_overrides_a_role_default(): void
    {
        $staff = User::factory()->staff()->create();

        $this->assertFalse($staff->hasPermission('reports'));

        $staff->permissionOverrides()->create([
            'permission' => 'reports',
            'granted' => true,
        ]);

        $this->assertTrue($staff->hasPermission('reports'));
        $this->assertTrue(Gate::forUser($staff)->allows('reports'));
        $this->assertContains('reports', $staff->effectivePermissions());
    }

    public function test_an_explicit_denial_overrides_a_role_default(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->hasPermission('reports'));

        $admin->permissionOverrides()->create([
            'permission' => 'reports',
            'granted' => false,
        ]);

        $this->assertFalse($admin->hasPermission('reports'));
        $this->assertFalse(Gate::forUser($admin)->allows('reports'));
        $this->assertNotContains('reports', $admin->effectivePermissions());
    }

    public function test_an_inactive_superadmin_loses_the_bypass(): void
    {
        $user = User::factory()->superadmin()->suspended()->create();

        $this->assertFalse($user->hasPermission('users'));
        $this->assertFalse(Gate::forUser($user)->allows('users'));
        $this->assertSame([], $user->effectivePermissions());
    }

    public function test_permission_middleware_rejects_unauthorized_requests_server_side(): void
    {
        Route::middleware(['web', 'auth', 'permission:users'])
            ->get('/_test/permission-protected', fn (): string => 'allowed');

        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get('/_test/permission-protected')
            ->assertForbidden();

        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->get('/_test/permission-protected')
            ->assertOk()
            ->assertSee('allowed');
    }

    public function test_unknown_permission_keys_are_denied(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->assertFalse($superadmin->hasPermission('does_not_exist'));
        $this->assertFalse(Gate::forUser($superadmin)->allows('does_not_exist'));
    }
}
