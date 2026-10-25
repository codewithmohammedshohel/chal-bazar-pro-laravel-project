<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_relationships_are_wired_in_both_directions(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->forCategory($category)->rice()->create();
        $variant = ProductVariant::factory()->for($product)->kg()->create();
        $media = ProductMedia::factory()->for($product)->create();

        $this->assertTrue($product->category->is($category));
        $this->assertTrue($category->products->contains($product));
        $this->assertTrue($product->variants->contains($variant));
        $this->assertTrue($product->media->contains($media));
        $this->assertTrue($variant->product->is($product));
        $this->assertTrue($media->product->is($product));
    }

    public function test_category_slugs_are_unique(): void
    {
        Category::factory()->create(['slug' => 'duplicate-category']);

        $this->expectException(QueryException::class);

        Category::factory()->create(['slug' => 'duplicate-category']);
    }

    public function test_product_slugs_are_unique(): void
    {
        $category = Category::factory()->create();
        Product::factory()->forCategory($category)->create(['slug' => 'duplicate-product']);

        $this->expectException(QueryException::class);

        Product::factory()->forCategory($category)->create(['slug' => 'duplicate-product']);
    }

    public function test_variant_unit_is_unique_per_product(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->kg()->create();

        $this->expectException(QueryException::class);

        ProductVariant::factory()->for($product)->kg()->create();
    }

    public function test_the_same_unit_is_allowed_on_different_products(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();

        ProductVariant::factory()->for($first)->kg()->create();
        ProductVariant::factory()->for($second)->kg()->create();

        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_variant_prices_are_cast_and_private_costs_are_hidden(): void
    {
        $product = Product::factory()->create();

        $variant = ProductVariant::factory()->for($product)->kg()->create([
            'weight_kg' => 1,
            'retail_price' => 72.50,
            'wholesale_price' => 68.00,
            'purchase_cost' => 60.25,
        ]);

        $variant->refresh();

        $this->assertSame('72.50', $variant->retail_price);
        $this->assertSame('1.000', $variant->weight_kg);
        $this->assertArrayNotHasKey('purchase_cost', $variant->toArray());
        $this->assertArrayNotHasKey('wholesale_price', $variant->toArray());
    }

    public function test_products_and_categories_support_soft_deletes(): void
    {
        $product = Product::factory()->create();
        $category = $product->category;

        $product->delete();
        $category->delete();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_products_can_be_scoped_by_type_and_active_status(): void
    {
        $category = Category::factory()->create();
        $rice = Product::factory()->forCategory($category)->rice()->create();
        $other = Product::factory()->forCategory($category)->other()->create();
        $inactive = Product::factory()->forCategory($category)->rice()->inactive()->create();

        $this->assertTrue(Product::query()->ofType(Product::TYPE_RICE)->active()->get()->contains($rice));
        $this->assertFalse(Product::query()->ofType(Product::TYPE_RICE)->active()->get()->contains($inactive));
        $this->assertTrue(Product::query()->ofType(Product::TYPE_OTHER)->get()->contains($other));
    }
}
