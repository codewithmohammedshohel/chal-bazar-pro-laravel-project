<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'type' => Product::TYPE_RICE,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 999999),
            'title' => Str::title($title),
            'name_bn' => 'ডেমো পণ্য',
            'name_en' => Str::title($title),
            'description' => fake()->paragraph(),
            'mill_name' => null,
            'supplier' => null,
            'grain_type' => null,
            'processing_method' => null,
            'is_featured' => false,
            'is_active' => true,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function rice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Product::TYPE_RICE,
            'mill_name' => 'Demo Rice Mill',
            'supplier' => 'Demo Supplier',
            'grain_type' => 'Miniket',
            'processing_method' => 'Auto sortex',
        ]);
    }

    public function other(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Product::TYPE_OTHER,
            'mill_name' => null,
            'supplier' => null,
            'grain_type' => null,
            'processing_method' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->getKey(),
        ]);
    }
}
