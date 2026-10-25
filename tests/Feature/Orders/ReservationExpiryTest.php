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

class ReservationExpiryTest extends TestCase
{
    use BuildsStockedCatalog, RefreshDatabase;

    private OrderService $orders;

    private User $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('orders.reservation_ttl_hours', 24);

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

    private function reservation(Order $order): StockReservation
    {
        return StockReservation::query()->where('order_id', $order->id)->firstOrFail();
    }

    private function level(int $variantId): StockLevel
    {
        return StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->firstOrFail();
    }

    private function makeDue(Order $order): void
    {
        StockReservation::query()
            ->where('order_id', $order->id)
            ->update(['expires_at' => now()->subMinute()]);
    }

    public function test_new_pending_reservation_receives_the_configured_expiry(): void
    {
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->place($variant->id);

        $reservation = $this->reservation($order);

        $this->assertSame(StockReservation::STATUS_PENDING, $reservation->status);
        $this->assertNotNull($reservation->expires_at);
        $this->assertTrue($reservation->expires_at->greaterThan(now()->addHours(23)));
        $this->assertTrue($reservation->expires_at->lessThan(now()->addHours(25)));
    }

    public function test_expired_pending_reservation_is_released_and_order_cancelled_once(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);
        $this->makeDue($order);

        $this->artisan('orders:expire-reservations')->assertSuccessful();

        $order = $order->fresh();
        $reservation = $this->reservation($order);
        $level = $this->level($variant->id);

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame('expiry', $order->cancelled_by);
        $this->assertNotNull($order->cancelled_at);

        $this->assertSame('50.0000', $level->on_hand_kg);
        $this->assertSame('0.0000', $level->reserved_kg);
        $this->assertSame('50.0000', $level->available_kg);

        $this->assertSame(StockReservation::STATUS_RELEASED, $reservation->status);
        $this->assertSame(StockReservation::RELEASE_EXPIRED, $reservation->release_reason);
        $this->assertNotNull($reservation->released_at);

        $event = $order->statusEvents->last();
        $this->assertSame(Order::STATUS_CANCELLED, $event->to_status);
        $this->assertSame(Order::STATUS_PENDING, $event->from_status);
        $this->assertSame('system', $event->actor_role);
        $this->assertNull($event->actor_user_id);
        $this->assertStringContainsString('স্বয়ংক্রিয়', (string) $event->note);

        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
    }

    public function test_non_expired_reservation_is_left_untouched(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);

        $this->artisan('orders:expire-reservations')->assertSuccessful();

        $level = $this->level($variant->id);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame('2.0000', $level->reserved_kg);
        $this->assertSame(StockReservation::STATUS_PENDING, $this->reservation($order)->status);
        $this->assertSame(0, StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
    }

    public function test_accepted_order_is_not_expired_even_if_its_expiry_is_forced_into_the_past(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);

        $this->assertNotNull($this->reservation($order)->expires_at);

        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);

        $this->assertNull($this->reservation($order)->fresh()->expires_at);

        $this->makeDue($order);

        $this->artisan('orders:expire-reservations')->assertSuccessful();

        $this->assertSame(Order::STATUS_ACCEPTED, $order->fresh()->status);
        $this->assertSame(StockReservation::STATUS_PENDING, $this->reservation($order)->status);
        $this->assertSame('2.0000', $this->level($variant->id)->reserved_kg);
        $this->assertSame(0, StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
    }

    public function test_dispatched_order_is_not_expired(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);

        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);
        $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);

        $this->makeDue($order);

        $this->artisan('orders:expire-reservations')->assertSuccessful();

        $this->assertSame(Order::STATUS_IN_TRANSIT, $order->fresh()->status);
        $this->assertSame(StockReservation::STATUS_FULFILLED, $this->reservation($order)->status);
        $this->assertSame('48.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(0, StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
    }

    public function test_repeated_expiry_runs_do_not_release_or_append_events_twice(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);
        $this->makeDue($order);

        $this->artisan('orders:expire-reservations')->assertSuccessful();
        $this->artisan('orders:expire-reservations')->assertSuccessful();

        $this->assertSame('0.0000', $this->level($variant->id)->reserved_kg);
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
        $this->assertSame(1, $order->fresh()->statusEvents()
            ->where('to_status', Order::STATUS_CANCELLED)
            ->count());
    }

    public function test_customer_cancellation_and_expiry_cannot_release_twice(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->place($variant->id, 2);
        $this->makeDue($order);

        $this->orders->cancelByCustomer($order, $this->customer);

        $this->artisan('orders:expire-reservations')->assertSuccessful();

        $order = $order->fresh();
        $reservation = $this->reservation($order);

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame('customer', $order->cancelled_by);
        $this->assertSame(StockReservation::RELEASE_CUSTOMER, $reservation->release_reason);
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());
        $this->assertSame(1, $order->statusEvents()
            ->where('to_status', Order::STATUS_CANCELLED)
            ->count());
    }

    public function test_expiry_and_acceptance_are_serialised_by_the_order_row(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);

        // Acceptance wins: expiry becomes a no-op.
        $acceptedOrder = $this->place($variant->id, 1, 'accept-wins');
        $this->orders->transition($acceptedOrder, Order::STATUS_ACCEPTED, $this->admin);
        $this->makeDue($acceptedOrder);

        $this->assertFalse($this->orders->expireOrderReservations($acceptedOrder->id));
        $this->assertSame(Order::STATUS_ACCEPTED, $acceptedOrder->fresh()->status);
        $this->assertSame('1.0000', $this->level($variant->id)->reserved_kg);

        // Expiry wins: the later acceptance must be rejected.
        $expiringOrder = $this->place($variant->id, 1, 'expiry-wins');
        $this->makeDue($expiringOrder);

        $this->assertTrue($this->orders->expireOrderReservations($expiringOrder->id));

        try {
            $this->orders->transition($expiringOrder->fresh(), Order::STATUS_ACCEPTED, $this->admin);
            $this->fail('Acceptance after expiry should have been rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(Order::STATUS_CANCELLED, $expiringOrder->fresh()->status);
        $this->assertSame('1.0000', $this->level($variant->id)->reserved_kg);
    }

    public function test_expiry_command_limit_option_rejects_invalid_values(): void
    {
        $this->artisan('orders:expire-reservations', ['--limit' => '0'])
            ->assertExitCode(1);
    }
}
