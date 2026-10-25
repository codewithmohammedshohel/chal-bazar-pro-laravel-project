<?php

namespace Tests\Feature\Orders;

use App\Models\Invoice;
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

class PlaceOrderTest extends TestCase
{
    use BuildsStockedCatalog, RefreshDatabase;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = $this->app->make(OrderService::class);
    }

    private function place(User $user, array $items, ?string $key = null): Order
    {
        return $this->orders->place(
            customer: $user,
            items: $items,
            deliveryAddress: '১২৩ টেস্ট রোড, চট্টগ্রাম',
            lat: DeliveryFeeService::SHOP_LAT,
            lng: DeliveryFeeService::SHOP_LNG,
            idempotencyKey: $key,
        );
    }

    public function test_order_records_snapshots_totals_invoice_and_reservations(): void
    {
        $user = User::factory()->customer()->create();
        $variant = $this->makeVariant(price: 72.50, onHandKg: 50);

        $order = $this->place($user, [['variant_id' => $variant->id, 'quantity' => 2]]);

        $order->load(['items', 'invoice', 'statusEvents']);

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(Order::PAYMENT_COD, $order->payment_mode);
        $this->assertSame('145.00', $order->subtotal);
        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('145.00', $order->total);
        $this->assertSame('72.50', $order->items[0]->unit_price);
        $this->assertSame(2, $order->items[0]->quantity);
        $this->assertSame('145.00', $order->items[0]->line_total);
        $this->assertSame('1.000', $order->items[0]->weight_kg);
        $this->assertNotNull($order->invoice);
        $this->assertSame('pending', $order->statusEvents[0]->to_status);

        $level = StockLevel::where('product_variant_id', $variant->id)->first();
        $this->assertSame('50.0000', $level->on_hand_kg);   // physical unchanged
        $this->assertSame('2.0000', $level->reserved_kg);   // reserved
        $this->assertSame('48.0000', $level->available_kg);

        $this->assertDatabaseHas('stock_reservations', [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'status' => StockReservation::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_RESERVE,
            'stock_bucket' => StockMovement::BUCKET_RESERVED,
            'direction' => StockMovement::DIRECTION_IN,
        ]);
    }

    public function test_insufficient_stock_rejects_the_order(): void
    {
        $user = User::factory()->customer()->create();
        $variant = $this->makeVariant(onHandKg: 1);

        try {
            $this->place($user, [['variant_id' => $variant->id, 'quantity' => 2]]);
            $this->fail('Expected insufficient stock validation failure.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertSame('0.0000', StockLevel::first()->reserved_kg);
    }

    public function test_multi_item_order_is_atomic_when_one_line_lacks_stock(): void
    {
        $user = User::factory()->customer()->create();
        $ok = $this->makeVariant(price: 10, onHandKg: 100);
        $scarce = $this->makeVariant(price: 20, onHandKg: 1);

        try {
            $this->place($user, [
                ['variant_id' => $ok->id, 'quantity' => 1],
                ['variant_id' => $scarce->id, 'quantity' => 5],
            ]);
            $this->fail('Expected insufficient stock validation failure.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('0.0000', StockLevel::where('product_variant_id', $ok->id)->first()->reserved_kg);
    }

    public function test_idempotency_key_prevents_duplicate_orders(): void
    {
        $user = User::factory()->customer()->create();
        $variant = $this->makeVariant(onHandKg: 100);

        $first = $this->place($user, [['variant_id' => $variant->id, 'quantity' => 1]], 'checkout-key-1');
        $second = $this->place($user, [['variant_id' => $variant->id, 'quantity' => 1]], 'checkout-key-1');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('1.0000', StockLevel::first()->reserved_kg);
    }

    public function test_price_snapshot_survives_later_product_price_changes(): void
    {
        $user = User::factory()->customer()->create();
        $variant = $this->makeVariant(price: 72.50, onHandKg: 100);

        $order = $this->place($user, [['variant_id' => $variant->id, 'quantity' => 2]]);

        $variant->update(['retail_price' => 999.00]);

        $order->refresh()->load('items');

        $this->assertSame('72.50', $order->items[0]->unit_price);
        $this->assertSame('145.00', $order->total);
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->post(route('checkout.store'), [
            'delivery_address' => '১২৩ টেস্ট রোড, চট্টগ্রাম',
            'delivery_lat' => DeliveryFeeService::SHOP_LAT,
            'delivery_lng' => DeliveryFeeService::SHOP_LNG,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_authenticated_customer_can_checkout_from_the_cart(): void
    {
        $user = User::factory()->customer()->create();
        $variant = $this->makeVariant(price: 72.50, onHandKg: 100);

        $this->actingAs($user)
            ->post(route('cart.add-item'), [
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ])
            ->assertRedirect(route('cart.index'));

        $checkout = $this->actingAs($user)->get(route('checkout.show'))->assertOk();
        $this->assertNotNull($checkout->viewData('idempotencyKey'));

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_address' => '১২৩ টেস্ট রোড, চট্টগ্রাম',
            'delivery_lat' => DeliveryFeeService::SHOP_LAT,
            'delivery_lng' => DeliveryFeeService::SHOP_LNG,
            'customer_note' => 'সকালে ডেলিভারি দিন',
            'idempotency_key' => $checkout->viewData('idempotencyKey'),
        ]);

        $order = Order::firstOrFail();

        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('145.00', $order->total);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, app(\App\Services\CartService::class)->count());
    }
}
