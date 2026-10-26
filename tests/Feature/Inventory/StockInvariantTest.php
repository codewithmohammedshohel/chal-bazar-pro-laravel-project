<?php

namespace Tests\Feature\Inventory;

use App\Models\Order;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use App\Support\Kilograms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Orders\BuildsStockedCatalog;
use Tests\TestCase;

class StockInvariantTest extends TestCase
{
    use BuildsStockedCatalog, RefreshDatabase;

    private OrderService $orders;

    private User $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = $this->app->make(OrderService::class);
        $this->customer = User::factory()->customer()->create();
        $this->admin = User::factory()->admin()->create();
    }

    private function place(int $variantId, int $quantity, ?string $key = null): Order
    {
        return $this->orders->place(
            customer: $this->customer,
            items: [['variant_id' => $variantId, 'quantity' => $quantity]],
            deliveryAddress: '১২৩ টেস্ট রোড, চট্টগ্রাম',
            lat: DeliveryFeeService::SHOP_LAT,
            lng: DeliveryFeeService::SHOP_LNG,
            idempotencyKey: $key,
        );
    }

    private function level(int $variantId): StockLevel
    {
        return StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->firstOrFail();
    }

    private function dispatch(Order $order): Order
    {
        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);
        $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);

        return $order->fresh();
    }

    public function test_reservation_changes_only_the_reserved_bucket(): void
    {
        $variant = $this->makeVariant(onHandKg: 100);
        $order = $this->place($variant->id, 2);

        $level = $this->level($variant->id);

        $this->assertSame('100.0000', $level->on_hand_kg);
        $this->assertSame('2.0000', $level->reserved_kg);
        $this->assertSame('98.0000', $level->available_kg);
        $this->assertSame(0, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('stock_bucket', StockMovement::BUCKET_PHYSICAL)
            ->count());
    }

    public function test_dispatch_decrements_physical_stock_once_and_delivery_does_not_deduct_again(): void
    {
        $variant = $this->makeVariant(onHandKg: 100);
        $order = $this->place($variant->id, 2);

        $this->dispatch($order);

        $level = $this->level($variant->id);
        $this->assertSame('98.0000', $level->on_hand_kg);
        $this->assertSame('0.0000', $level->reserved_kg);

        $movementsBeforeDelivery = StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->count();

        $this->orders->transition($order->fresh(), Order::STATUS_DELIVERED, $this->admin);

        $this->assertSame('98.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame($movementsBeforeDelivery, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->count());
    }

    public function test_cancellation_releases_the_reservation_once_and_never_restocks_physical_stock(): void
    {
        $variant = $this->makeVariant(onHandKg: 100);
        $order = $this->place($variant->id, 2);

        $cancelled = $this->orders->cancelByCustomer($order, $this->customer);

        $level = $this->level($variant->id);
        $this->assertSame('100.0000', $level->on_hand_kg);
        $this->assertSame('0.0000', $level->reserved_kg);
        $this->assertSame('100.0000', $level->available_kg);
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $cancelled->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
        $this->assertSame(0, StockMovement::query()
            ->where('reference_id', (string) $cancelled->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());

        try {
            $this->orders->cancelByCustomer($cancelled, $this->customer);
            $this->fail('A second cancellation should have been rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $cancelled->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
    }

    public function test_a_competing_checkout_cannot_reserve_stock_beyond_availability(): void
    {
        $variant = $this->makeVariant(onHandKg: 5);

        $this->place($variant->id, 4, 'first-checkout');

        try {
            $this->place($variant->id, 2, 'second-checkout');
            $this->fail('The second checkout should have been rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $level = $this->level($variant->id);

        $this->assertSame('5.0000', $level->on_hand_kg);
        $this->assertSame('4.0000', $level->reserved_kg);
        $this->assertSame('1.0000', $level->available_kg);
        $this->assertSame(1, Order::query()->where('user_id', $this->customer->id)->count());
    }

    public function test_approved_return_increases_physical_stock_once_and_the_ledger_reconciles(): void
    {
        $variant = $this->makeVariant(onHandKg: 100);
        $order = $this->dispatch($this->place($variant->id, 3));
        $item = $order->items()->firstOrFail();

        $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '1.25']],
            'invariant-return',
            'যাচাইকৃত ফেরত — রিগ্রেশন পরীক্ষা',
        );

        $this->assertSame('98.2500', $this->level($variant->id)->on_hand_kg);
        $this->assertSame('98.2500', $this->level($variant->id)->available_kg);
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());

        $physicalDelta = StockMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('stock_bucket', StockMovement::BUCKET_PHYSICAL)
            ->get()
            ->reduce(
                static fn (int $carry, StockMovement $movement): int => $carry
                    + ($movement->direction === StockMovement::DIRECTION_IN ? 1 : -1)
                        * Kilograms::toUnits($movement->quantity_kg),
                0,
            );

        $reservedDelta = StockMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('stock_bucket', StockMovement::BUCKET_RESERVED)
            ->get()
            ->reduce(
                static fn (int $carry, StockMovement $movement): int => $carry
                    + ($movement->direction === StockMovement::DIRECTION_IN ? 1 : -1)
                        * Kilograms::toUnits($movement->quantity_kg),
                0,
            );

        $level = $this->level($variant->id);

        $this->assertSame(Kilograms::toUnits('100'), $level->onHandUnits() - $physicalDelta);
        $this->assertSame(0, $reservedDelta);
    }

    public function test_repeated_dispatch_requests_are_rejected_without_new_movements(): void
    {
        $variant = $this->makeVariant(onHandKg: 100);
        $order = $this->dispatch($this->place($variant->id, 2));

        $count = StockMovement::query()->where('reference_id', (string) $order->id)->count();

        try {
            $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);
            $this->fail('A repeated dispatch should have been rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($count, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->count());
        $this->assertSame('98.0000', $this->level($variant->id)->on_hand_kg);
    }
}
