<?php

namespace Tests\Feature\Orders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;

trait BuildsStockedCatalog
{
    private function makeVariant(
        float $price = 72.00,
        float $weightKg = 1.0,
        string $unit = ProductVariant::UNIT_KG,
        float $onHandKg = 100.0,
        bool $active = true,
        ?Product $product = null,
    ): ProductVariant {
        if ($product === null) {
            $category = Category::factory()->create();

            $product = Product::factory()->forCategory($category)->rice()->create([
                'is_active' => true,
                'title' => 'Test Rice '.uniqid(),
            ]);
        }

        $variant = ProductVariant::factory()->for($product)->create([
            'unit' => $unit,
            'weight_kg' => $weightKg,
            'retail_price' => $price,
            'is_active' => $active,
            'label_en' => $weightKg.' kg',
            'label_bn' => $weightKg.' কেজি',
        ]);

        StockLevel::create([
            'product_variant_id' => $variant->id,
            'on_hand_kg' => $onHandKg,
            'reserved_kg' => 0,
            'available_kg' => $onHandKg,
        ]);

        return $variant->fresh();
    }
}
