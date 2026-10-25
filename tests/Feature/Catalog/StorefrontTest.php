<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function riceProduct(Category $category, array $attributes = []): Product
    {
        $product = Product::factory()->forCategory($category)->rice()->create($attributes);

        ProductVariant::factory()->for($product)->kg()->create(['retail_price' => 72.00]);
        ProductMedia::factory()->for($product)->create([
            'url' => '/images/demo/rice-1.svg',
            'alt_bn' => 'ডেমো চালের ছবি',
            'alt_en' => 'Demo rice image',
        ]);

        return $product;
    }

    public function test_the_homepage_lists_only_active_rice_products(): void
    {
        $category = Category::factory()->create(['slug' => 'rice', 'name_bn' => 'চাল', 'name_en' => 'Rice']);
        $visible = $this->riceProduct($category, ['title' => 'Visible Miniket', 'slug' => 'visible-miniket']);
        $inactive = $this->riceProduct($category, ['title' => 'Hidden Rice', 'slug' => 'hidden-rice', 'is_active' => false]);
        $grocery = Product::factory()->forCategory($category)->other()->create(['title' => 'Hidden Grocery', 'slug' => 'hidden-grocery']);

        $this->get('/')
            ->assertOk()
            ->assertSee($visible->title)
            ->assertDontSee($inactive->title)
            ->assertDontSee($grocery->title);
    }

    public function test_the_other_products_page_lists_only_other_products(): void
    {
        $category = Category::factory()->create();
        $rice = $this->riceProduct($category, ['title' => 'Rice Only', 'slug' => 'rice-only']);
        $other = Product::factory()->forCategory($category)->other()->create(['title' => 'Grocery Only', 'slug' => 'grocery-only']);
        ProductVariant::factory()->for($other)->kg()->create(['retail_price' => 135.00]);

        $this->get('/other-products')
            ->assertOk()
            ->assertSee($other->title)
            ->assertDontSee($rice->title);
    }

    public function test_products_from_inactive_categories_are_hidden(): void
    {
        $inactiveCategory = Category::factory()->inactive()->create();
        $product = $this->riceProduct($inactiveCategory, ['title' => 'Hidden Category Rice', 'slug' => 'hidden-category-rice']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee($product->title);

        $this->get(route('storefront.product', $product))
            ->assertNotFound();
    }

    public function test_search_filters_products_by_name(): void
    {
        $category = Category::factory()->create();
        $alpha = $this->riceProduct($category, ['title' => 'Alpha Special Rice', 'slug' => 'alpha-special']);
        $beta = $this->riceProduct($category, ['title' => 'Beta Special Rice', 'slug' => 'beta-special']);

        $this->get('/?q=Alpha')
            ->assertOk()
            ->assertSee($alpha->title)
            ->assertDontSee($beta->title);
    }

    public function test_category_filter_narrows_the_listing(): void
    {
        $riceCategory = Category::factory()->create(['slug' => 'rice', 'name_bn' => 'চাল', 'name_en' => 'Rice']);
        $aromaticCategory = Category::factory()->create(['slug' => 'aromatic', 'name_bn' => 'সুগন্ধি', 'name_en' => 'Aromatic']);

        $plain = $this->riceProduct($riceCategory, ['title' => 'Plain Rice Item', 'slug' => 'plain-rice-item']);
        $aromatic = $this->riceProduct($aromaticCategory, ['title' => 'Aromatic Rice Item', 'slug' => 'aromatic-rice-item']);

        $this->get('/?category=aromatic')
            ->assertOk()
            ->assertSee($aromatic->title)
            ->assertDontSee($plain->title);
    }

    public function test_the_listing_is_paginated(): void
    {
        $category = Category::factory()->create();

        foreach (range(1, 13) as $index) {
            $this->riceProduct($category, [
                'title' => sprintf('Paged Rice %02d', $index),
                'slug' => sprintf('paged-rice-%02d', $index),
            ]);
        }

        $firstPage = $this->get('/')->assertOk();
        $paginator = $firstPage->viewData('products');

        $this->assertSame(12, $paginator->count());
        $this->assertSame(13, $paginator->total());
        $this->assertTrue($paginator->hasPages());

        $secondPage = $this->get('/?page=2')->assertOk();
        $this->assertSame(1, $secondPage->viewData('products')->count());
    }

    public function test_a_product_detail_page_resolves_by_slug_and_renders_variants_and_media(): void
    {
        $category = Category::factory()->create(['slug' => 'rice', 'name_bn' => 'চাল', 'name_en' => 'Rice']);
        $product = Product::factory()->forCategory($category)->rice()->create([
            'title' => 'Detail Miniket',
            'slug' => 'detail-miniket',
            'description' => 'A demo rice product description.',
        ]);

        ProductVariant::factory()->for($product)->kg()->create(['retail_price' => 72.00]);
        ProductVariant::factory()->for($product)->sack25()->create(['retail_price' => 1750.00]);
        ProductMedia::factory()->for($product)->create([
            'url' => '/images/demo/rice-1.svg',
            'alt_bn' => 'ডেমো চালের ছবি',
            'alt_en' => 'Demo rice image',
        ]);

        $this->get(route('storefront.product', $product))
            ->assertOk()
            ->assertSee('Detail Miniket')
            ->assertSee('১ কেজি')
            ->assertSee('২৫ কেজি বস্তা')
            ->assertSee('1,750.00')
            ->assertSee('/images/demo/rice-1.svg')
            ->assertSee('A demo rice product description.');
    }

    public function test_inactive_variants_are_not_rendered_publicly(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->forCategory($category)->rice()->create(['slug' => 'variant-check']);

        ProductVariant::factory()->for($product)->kg()->create(['retail_price' => 70.00]);
        ProductVariant::factory()->for($product)->sack25()->inactive()->create(['retail_price' => 1700.00]);

        $this->get(route('storefront.product', $product))
            ->assertOk()
            ->assertSee('1 kg')
            ->assertDontSee('25 kg sack')
            ->assertDontSee('1,700.00');
    }

    public function test_missing_and_inactive_products_return_404(): void
    {
        $category = Category::factory()->create();
        $inactive = $this->riceProduct($category, ['slug' => 'inactive-detail', 'is_active' => false]);

        $this->get('/products/does-not-exist')->assertNotFound();
        $this->get('/products/'.$inactive->slug)->assertNotFound();
    }

    public function test_public_views_never_expose_purchase_cost_or_wholesale_price(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->forCategory($category)->rice()->create(['slug' => 'private-cost-check']);

        ProductVariant::factory()->for($product)->kg()->create([
            'retail_price' => 72.00,
            'wholesale_price' => 888.88,
            'purchase_cost' => 987.65,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('987.65')
            ->assertDontSee('888.88')
            ->assertDontSee('purchase_cost')
            ->assertDontSee('wholesale_price');

        $this->get(route('storefront.product', $product))
            ->assertOk()
            ->assertDontSee('987.65')
            ->assertDontSee('888.88')
            ->assertDontSee('purchase_cost')
            ->assertDontSee('wholesale_price');
    }

    public function test_the_about_page_renders(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('আমাদের সম্পর্কে')
            ->assertSee('ডেমো');
    }
}
