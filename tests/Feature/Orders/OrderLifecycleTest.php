<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
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

    private function place(int $variantId, int $quantity = 2, ?string $key = null): Order
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

    public function test_dispatch_decrements_physical_stock_once_and_fulfils_reservation(): void
    {
        $variant = $this->makeVariant(price: 72.50, onHandKg: 50);
        $order = $this->place($variant->id, 2);

        // Placement reserved only; physical stock is untouched.
        $this->assertSame('50.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame('2.0000', $this->level($variant->id)->reserved_kg);

        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);
        $order = $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);

        $level = $this->level($variant->id);

        $this->assertSame(Order::STATUS_IN_TRANSIT, $order->status);
        $this->assertNotNull($order->dispatched_at);
        $this->assertSame('48.0000', $level->on_hand_kg);
        $this->assertSame('0.0000', $level->reserved_kg);
        $this->assertSame('48.0000', $level->available_kg);

        $reservation = StockReservation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(StockReservation::STATUS_FULFILLED, $reservation->status);
        $this->assertNotNull($reservation->fulfilled_at);
        $this->assertNull($reservation->released_at);

        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RESERVE)
            ->count());

        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_DISPATCH)
            ->where('stock_bucket', StockMovement::BUCKET_PHYSICAL)
            ->where('direction', StockMovement::DIRECTION_OUT)
            ->count());

        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_DISPATCH)
            ->where('stock_bucket', StockMovement::BUCKET_RESERVED)
            ->where('direction', StockMovement::DIRECTION_OUT)
            ->count());
    }

    public function test_delivery_confirmation_does_not_deduct_stock_a_second_time(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);

        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);
        $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);
        $order = $this->orders->transition($order->fresh(), Order::STATUS_DELIVERED, $this->admin);

        $level = $this->level($variant->id);

        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertSame('48.0000', $level->on_hand_kg);
        $this->assertSame('0.0000', $level->reserved_kg);

        // reserve + dispatch physical + dispatch reserved; nothing on delivery.
        $this->assertSame(3, StockMovement::query()->where('reference_id', (string) $order->id)->count());
    }

    public function test_every_transition_appends_an_ordered_status_event(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 1);

        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin, 'গ্রহণ করা হয়েছে।');
        $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);
        $this->orders->transition($order->fresh(), Order::STATUS_DELIVERED, $this->admin);

        $events = $order->fresh()->statusEvents;

        $this->assertSame(
            ['pending', 'accepted', 'in_transit', 'delivered'],
            $events->pluck('to_status')->all(),
        );

        $this->assertSame(
            [null, 'pending', 'accepted', 'in_transit'],
            $events->pluck('from_status')->all(),
        );

        $this->assertSame($this->customer->id, $events[0]->actor_user_id);
        $this->assertSame($this->admin->id, $events[1]->actor_user_id);
        $this->assertSame('admin', $events[1]->actor_role);
    }

    public function test_customer_cancellation_releases_the_reservation_exactly_once(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);

        $cancelled = $this->orders->cancelByCustomer($order, $this->customer);

        $this->assertSame(Order::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('customer', $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);

        $level = $this->level($variant->id);
        $this->assertSame('50.0000', $level->on_hand_kg);
        $this->assertSame('0.0000', $level->reserved_kg);
        $this->assertSame('50.0000', $level->available_kg);

        $reservation = StockReservation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(StockReservation::STATUS_RELEASED, $reservation->status);
        $this->assertNotNull($reservation->released_at);
        $this->assertNull($reservation->fulfilled_at);

        $releaseMovements = StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count();

        $this->assertSame(1, $releaseMovements);
        $this->assertSame('cancelled', $cancelled->statusEvents->last()->to_status);

        // A repeat cancellation is rejected and must not release twice.
        try {
            $this->orders->cancelByCustomer($cancelled, $this->customer);
            $this->fail('Second cancellation should have been rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('0.0000', $this->level($variant->id)->reserved_kg);
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
    }

    public function test_customer_cancellation_is_rejected_after_dispatch(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);

        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);
        $dispatched = $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);

        try {
            $this->orders->cancelByCustomer($dispatched, $this->customer);
            $this->fail('Cancellation after dispatch should have been rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(Order::STATUS_IN_TRANSIT, $dispatched->fresh()->status);
        $this->assertSame('48.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(0, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
    }

    public function test_shop_cancellation_after_dispatch_does_not_restock_goods(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);

        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);
        $dispatched = $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);

        $cancelled = $this->orders->cancelByShop($dispatched, $this->admin, 'গ্রাহক ফোন ধরছেন না।');

        $this->assertSame(Order::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('shop', $cancelled->cancelled_by);

        $level = $this->level($variant->id);
        $this->assertSame('48.0000', $level->on_hand_kg);
        $this->assertSame('0.0000', $level->reserved_kg);

        $this->assertSame(0, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->whereIn('movement_type', [StockMovement::TYPE_RELEASE, StockMovement::TYPE_RETURN])
            ->count());

        $this->assertSame('cancelled', $cancelled->statusEvents->last()->to_status);
        $this->assertSame('গ্রাহক ফোন ধরছেন না।', $cancelled->statusEvents->last()->note);
    }

    public function test_verified_return_restocks_exactly_once(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);

        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);
        $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);
        $this->orders->transition($order->fresh(), Order::STATUS_DELIVERED, $this->admin);

        $this->assertSame('48.0000', $this->level($variant->id)->on_hand_kg);

        $this->orders->recordReturn($order, $this->admin, [$variant->id => 1.5], 'return-key-1');

        $this->assertSame('49.5000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame('49.5000', $this->level($variant->id)->available_kg);

        // The same operation key must not restock the same goods twice.
        $this->orders->recordReturn($order, $this->admin, [$variant->id => 1.5], 'return-key-1');

        $this->assertSame('49.5000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());

        // A distinct, newly verified return may legitimately restock again.
        $this->orders->recordReturn($order, $this->admin, [$variant->id => 0.5], 'return-key-2');

        $this->assertSame('50.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(2, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());
    }

    public function test_return_cannot_restock_before_the_goods_were_dispatched(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);

        try {
            $this->orders->recordReturn($order, $this->admin, [$variant->id => 1.0], 'early-return');
            $this->fail('A return before dispatch should have been rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('50.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(0, StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());
    }

    public function test_return_rejects_a_variant_that_is_not_on_the_order(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $other = $this->makeVariant(onHandKg: 50);

        $order = $this->place($variant->id, 1);
        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);
        $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);

        try {
            $this->orders->recordReturn($order, $this->admin, [$other->id => 1.0], 'return-wrong-variant');
            $this->fail('A variant outside the order should have been rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('50.0000', $this->level($other->id)->on_hand_kg);
        $this->assertSame(0, StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());
    }
}
