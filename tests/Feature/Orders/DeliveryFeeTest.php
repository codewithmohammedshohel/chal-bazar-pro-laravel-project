<?php

namespace Tests\Feature\Orders;

use App\Services\DeliveryFeeService;
use Tests\TestCase;

class DeliveryFeeTest extends TestCase
{
    private DeliveryFeeService $delivery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->delivery = new DeliveryFeeService;
    }

    public function test_distance_uses_approved_shop_coordinates(): void
    {
        $this->assertSame(0.0, round($this->delivery->distanceKm(
            DeliveryFeeService::SHOP_LAT,
            DeliveryFeeService::SHOP_LNG,
        ), 6));
    }

    public function test_ten_kilometres_is_free(): void
    {
        $this->assertSame(0.0, $this->delivery->feeForDistance(10.0));
        $this->assertSame(0.0, $this->delivery->feeForDistance(9.999));
        $this->assertSame(0.0, $this->delivery->feeForDistance(0.0));
    }

    public function test_just_above_ten_kilometres_charges_one_block(): void
    {
        $this->assertSame(15.0, $this->delivery->feeForDistance(10.001));
        $this->assertSame(15.0, $this->delivery->feeForDistance(10.9));
    }

    public function test_larger_distances_round_up_to_the_next_kilometre(): void
    {
        $this->assertSame(15.0, $this->delivery->feeForDistance(11.0));
        $this->assertSame(30.0, $this->delivery->feeForDistance(11.5));
        $this->assertSame(225.0, $this->delivery->feeForDistance(25.0));
    }

    public function test_quote_returns_distance_and_fee_together(): void
    {
        $quote = $this->delivery->quote(DeliveryFeeService::SHOP_LAT, DeliveryFeeService::SHOP_LNG);

        $this->assertSame(0.0, $quote['distance_km']);
        $this->assertSame(0.0, $quote['delivery_fee']);
    }
}
