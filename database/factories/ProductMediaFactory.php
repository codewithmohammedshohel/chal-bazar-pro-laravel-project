<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMedia>
 */
class ProductMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'kind' => ProductMedia::KIND_IMAGE,
            'url' => '/images/demo/rice-1.svg',
            'cloudinary_public_id' => null,
            'alt_bn' => 'ডেমো পণ্যের ছবি',
            'alt_en' => 'Demo product image',
            'sort_order' => 0,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ProductMedia::KIND_VIDEO,
            'url' => '/videos/demo.mp4',
            'alt_bn' => 'ডেমো ভিডিও',
            'alt_en' => 'Demo product video',
        ]);
    }
}
