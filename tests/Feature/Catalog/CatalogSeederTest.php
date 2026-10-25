<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_seeder_is_idempotent(): void
    {
        $this->seed(CatalogSeeder::class);

        $counts = [
            Category::query()->count(),
            Product::query()->count(),
            ProductVariant::query()->count(),
            ProductMedia::query()->count(),
        ];

        $this->seed(CatalogSeeder::class);

        $this->assertSame($counts, [
            Category::query()->count(),
            Product::query()->count(),
            ProductVariant::query()->count(),
            ProductMedia::query()->count(),
        ]);

        $this->assertSame([4, 10, 21, 10], $counts);
    }

    public function test_seeded_product_variants_point_to_existing_products(): void
    {
        $this->seed(CatalogSeeder::class);

        $productIds = Product::query()->pluck('id')->all();

        $this->assertSame(
            0,
            ProductVariant::query()->whereNotIn('product_id', $productIds)->count(),
        );

        $this->assertSame(
            0,
            ProductMedia::query()->whereNotIn('product_id', $productIds)->count(),
        );
    }

    public function test_the_seeder_does_not_create_user_accounts(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_seeded_catalog_is_visible_on_the_storefront(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('মিনিকেট চাল')
            ->assertSee('বাসমতী চাল');

        $this->get('/other-products')
            ->assertOk()
            ->assertSee('সয়াবিন তেল')
            ->assertSee('মসুর ডাল');
    }
}
