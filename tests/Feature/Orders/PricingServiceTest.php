<?php

namespace Tests\Feature\Orders;

use App\Models\Product;
use App\Services\DeliveryFeeService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use BuildsStockedCatalog, RefreshDatabase;

    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = $this->app->make(PricingService::class);
    }

    public function test_prices_are_recalculated_from_the_database(): void
    {
        $variant = $this->makeVariant(price: 72.50, onHandKg: 500);

        // Simulate a client trying to send a fake cheap price payload.
        $quote = $this->pricing->quote([
            ['variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => 1, 'line_total' => 2],
        ], DeliveryFeeService::SHOP_LAT, DeliveryFeeService::SHOP_LNG);

        $this->assertSame(145.0, $quote['subtotal']);
        $this->assertSame('72.50', $quote['lines'][0]['unit_price']);
        $this->assertSame('145.00', $quote['lines'][0]['line_total']);
        $this->assertSame(145.0, $quote['total']);
    }

    public function test_sack_prices_are_not_multiplied_by_weight(): void
    {
        $variant = $this->makeVariant(
            price: 1750.00,
            weightKg: 25.0,
            unit: 'sack_25kg',
            onHandKg: 500,
        );

        $quote = $this->pricing->quote([
            ['variant_id' => $variant->id, 'quantity' => 1],
        ], DeliveryFeeService::SHOP_LAT, DeliveryFeeService::SHOP_LNG);

        // Price stays 1750 — not 1750 * 25.
        $this->assertSame(1750.0, $quote['subtotal']);
        $this->assertSame(25.0, $quote['lines'][0]['requested_kg']);
    }

    public function test_duplicate_variant_rows_are_merged(): void
    {
        $variant = $this->makeVariant(price: 10.00, onHandKg: 100);

        $quote = $this->pricing->quote([
            ['variant_id' => $variant->id, 'quantity' => 2],
            ['variant_id' => $variant->id, 'quantity' => 3],
        ], DeliveryFeeService::SHOP_LAT, DeliveryFeeService::SHOP_LNG);

        $this->assertCount(1, $quote['lines']);
        $this->assertSame(5, $quote['lines'][0]['quantity']);
        $this->assertSame(50.0, $quote['subtotal']);
    }

    public function test_inactive_variants_are_rejected(): void
    {
        $variant = $this->makeVariant(active: false);

        $this->expectException(ValidationException::class);

        $this->pricing->quote([
            ['variant_id' => $variant->id, 'quantity' => 1],
        ], DeliveryFeeService::SHOP_LAT, DeliveryFeeService::SHOP_LNG);
    }

    public function test_inactive_products_are_rejected(): void
    {
        $variant = $this->makeVariant();
        $variant->product->update(['is_active' => false]);

        $this->expectException(ValidationException::class);

        $this->pricing->quote([
            ['variant_id' => $variant->id, 'quantity' => 1],
        ], DeliveryFeeService::SHOP_LAT, DeliveryFeeService::SHOP_LNG);
    }

    public function test_zero_and_oversized_quantities_are_rejected(): void
    {
        $variant = $this->makeVariant();

        foreach ([0, -1, PricingService::MAX_QUANTITY_PER_LINE + 1] as $quantity) {
            try {
                $this->pricing->quote([
                    ['variant_id' => $variant->id, 'quantity' => $quantity],
                ], DeliveryFeeService::SHOP_LAT, DeliveryFeeService::SHOP_LNG);

                $this->fail("Quantity {$quantity} should have been rejected.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_unknown_variant_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->pricing->quote([
            ['variant_id' => 999999, 'quantity' => 1],
        ], DeliveryFeeService::SHOP_LAT, DeliveryFeeService::SHOP_LNG);
    }

    public function test_empty_cart_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->pricing->quote([], DeliveryFeeService::SHOP_LAT, DeliveryFeeService::SHOP_LNG);
    }

    public function test_delivery_fee_is_added_to_the_total(): void
    {
        $variant = $this->makeVariant(price: 100.00, onHandKg: 100);

        // Far away point: roughly 100+ km from the shop.
        $quote = $this->pricing->quote([
            ['variant_id' => $variant->id, 'quantity' => 1],
        ], 23.8103, 90.4125); // Dhaka

        $this->assertGreaterThan(0, $quote['delivery_fee']);
        $this->assertSame(
            round($quote['subtotal'] + $quote['delivery_fee'], 2),
            $quote['total'],
        );
    }
}
