<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'unit' => ProductVariant::UNIT_KG,
            'weight_kg' => 1.000,
            'retail_price' => fake()->randomFloat(2, 50, 500),
            'wholesale_price' => null,
            'purchase_cost' => null,
            'label_bn' => '১ কেজি',
            'label_en' => '1 kg',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function kg(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit' => ProductVariant::UNIT_KG,
            'weight_kg' => 1.000,
            'label_bn' => '১ কেজি',
            'label_en' => '1 kg',
            'sort_order' => 0,
        ]);
    }

    public function sack25(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit' => ProductVariant::UNIT_SACK_25KG,
            'weight_kg' => 25.000,
            'label_bn' => '২৫ কেজি বস্তা',
            'label_en' => '25 kg sack',
            'sort_order' => 1,
        ]);
    }

    public function sack50(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit' => ProductVariant::UNIT_SACK_50KG,
            'weight_kg' => 50.000,
            'label_bn' => '৫০ কেজি বস্তা',
            'label_en' => '50 kg sack',
            'sort_order' => 2,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
