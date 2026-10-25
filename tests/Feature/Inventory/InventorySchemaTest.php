<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_level_exists_for_variant(): void
    {
        $variant = ProductVariant::factory()->kg()->create();
        $level = StockLevel::create([
            'product_variant_id' => $variant->id,
            'on_hand_kg' => 100,
            'reserved_kg' => 0,
            'available_kg' => 100,
        ]);

        $this->assertSame('100.0000', $level->on_hand_kg);
        $this->assertSame('100.0000', $level->available_kg);
        $this->assertSame(100.0, $level->available); // derived via accessor
    }

    public function test_reserved_kg_reduces_available(): void
    {
        $variant = ProductVariant::factory()->kg()->create();
        $level = StockLevel::create([
            'product_variant_id' => $variant->id,
            'on_hand_kg' => 50,
            'reserved_kg' => 20,
            'available_kg' => 30,
        ]);

        $this->assertSame(30.0, $level->available);
    }

    public function test_inventory_authorization_denies_unauthenticated(): void
    {
        $this->get(route('admin.catalog.variants'))->assertRedirect();
    }
}
