<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_catalog_administration(): void
    {
        $this->get(route('admin.catalog.categories'))->assertRedirect(route('login'));
        $this->get(route('admin.catalog.products'))->assertRedirect(route('login'));
        $this->get(route('admin.catalog.variants'))->assertRedirect(route('login'));
    }

    public function test_a_superadmin_can_open_the_category_and_product_administration_pages(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->forCategory($category)->create();

        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)
            ->get(route('admin.catalog.categories'))
            ->assertOk()
            ->assertSee('Catalog Administration')
            ->assertSee($category->slug);

        $this->actingAs($superadmin)
            ->get(route('admin.catalog.products'))
            ->assertOk()
            ->assertSee($product->title);
    }

    public function test_an_admin_can_open_the_category_and_product_administration_pages(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->forCategory($category)->create();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.catalog.categories'))
            ->assertOk()
            ->assertSee($category->slug);

        $this->actingAs($admin)
            ->get(route('admin.catalog.products'))
            ->assertOk()
            ->assertSee($product->title);
    }

    public function test_staff_and_customers_are_forbidden_from_catalog_administration(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->customer()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.catalog.categories'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.catalog.products'))
                ->assertForbidden();
        }
    }

    public function test_an_inactive_superadmin_is_logged_out_before_reaching_catalog_administration(): void
    {
        $suspended = User::factory()->superadmin()->suspended()->create();

        $this->actingAs($suspended)
            ->get(route('admin.catalog.categories'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_variant_administration_still_requires_the_inventory_permission(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.catalog.variants'))
            ->assertOk();

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.catalog.variants'))
            ->assertForbidden();

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.catalog.variants'))
            ->assertForbidden();
    }

    public function test_a_user_specific_grant_unlocks_only_the_granted_catalog_page(): void
    {
        $staff = User::factory()->staff()->create();

        $staff->permissionOverrides()->create([
            'permission' => 'catalog.products',
            'granted' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.catalog.products'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('admin.catalog.categories'))
            ->assertForbidden();
    }
}
