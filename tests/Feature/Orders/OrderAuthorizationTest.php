<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAuthorizationTest extends TestCase
{
    use BuildsStockedCatalog, RefreshDatabase;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = $this->app->make(OrderService::class);
    }

    private function place(User $customer, int $variantId, int $quantity = 1, ?string $key = null): Order
    {
        return $this->orders->place(
            customer: $customer,
            items: [['variant_id' => $variantId, 'quantity' => $quantity]],
            deliveryAddress: '১২৩ টেস্ট রোড, চট্টগ্রাম',
            lat: DeliveryFeeService::SHOP_LAT,
            lng: DeliveryFeeService::SHOP_LNG,
            idempotencyKey: $key,
        );
    }

    private function dispatch(Order $order, User $admin): Order
    {
        $this->orders->transition($order, Order::STATUS_ACCEPTED, $admin);
        $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $admin);

        return $order->fresh();
    }

    public function test_guests_are_redirected_to_login_from_order_pages(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->place($customer, $variant->id);

        $this->get(route('orders.index'))->assertRedirect(route('login'));
        $this->get(route('orders.show', $order))->assertRedirect(route('login'));
        $this->get(route('orders.invoice', $order))->assertRedirect(route('login'));
        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
    }

    public function test_customer_order_history_contains_only_their_own_orders(): void
    {
        $alice = User::factory()->customer()->create();
        $bob = User::factory()->customer()->create();
        $variant = $this->makeVariant(onHandKg: 100);

        $aliceOrder = $this->place($alice, $variant->id, 1, 'alice-1');
        $bobOrder = $this->place($bob, $variant->id, 1, 'bob-1');

        $this->actingAs($alice)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee($aliceOrder->code)
            ->assertDontSee($bobOrder->code);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $alice = User::factory()->customer()->create();
        $bob = User::factory()->customer()->create();
        $variant = $this->makeVariant(onHandKg: 10);

        $bobOrder = $this->place($bob, $variant->id);

        $this->actingAs($alice)
            ->get(route('orders.show', $bobOrder))
            ->assertForbidden();
    }

    public function test_customer_cannot_view_another_customers_invoice(): void
    {
        $alice = User::factory()->customer()->create();
        $bob = User::factory()->customer()->create();
        $variant = $this->makeVariant(onHandKg: 10);

        $bobOrder = $this->place($bob, $variant->id);

        $this->actingAs($alice)
            ->get(route('orders.invoice', $bobOrder))
            ->assertForbidden();
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $alice = User::factory()->customer()->create();
        $bob = User::factory()->customer()->create();
        $variant = $this->makeVariant(onHandKg: 10);

        $bobOrder = $this->place($bob, $variant->id);

        $this->actingAs($alice)
            ->post(route('orders.cancel', $bobOrder))
            ->assertForbidden();

        $this->assertSame(Order::STATUS_PENDING, $bobOrder->fresh()->status);
    }

    public function test_customer_can_cancel_their_own_eligible_order_over_http(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->place($customer, $variant->id, 2);

        $this->actingAs($customer)
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(
            '0.0000',
            StockLevel::query()->where('product_variant_id', $variant->id)->firstOrFail()->reserved_kg,
        );
    }

    public function test_customer_cannot_use_admin_order_processing(): void
    {
        $customer = User::factory()->customer()->create();
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->place($customer, $variant->id);

        $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.orders.show', $order))->assertForbidden();

        $this->actingAs($customer)
            ->post(route('admin.orders.transition', $order), ['status' => Order::STATUS_ACCEPTED])
            ->assertForbidden();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_staff_with_orders_permission_can_process_orders(): void
    {
        $customer = User::factory()->customer()->create();
        $staff = User::factory()->staff()->create();
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->place($customer, $variant->id);

        $this->assertTrue($staff->hasPermission('orders'));

        $this->actingAs($staff)
            ->get(route('admin.orders.index'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('admin.orders.show', $order))
            ->assertOk();

        $this->actingAs($staff)
            ->post(route('admin.orders.transition', $order), ['status' => Order::STATUS_ACCEPTED])
            ->assertStatus(302);

        $this->assertSame(Order::STATUS_ACCEPTED, $order->fresh()->status);
    }

    public function test_staff_without_inventory_permission_cannot_record_a_return(): void
    {
        $customer = User::factory()->customer()->create();
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->dispatch($this->place($customer, $variant->id, 2), $admin);

        $this->assertFalse($staff->hasPermission('inventory'));

        $this->actingAs($staff)
            ->post(route('admin.orders.return', $order), [
                'returns' => [$order->items()->firstOrFail()->id => '1'],
                'reason' => 'যাচাইকৃত ফেরত',
                'operation_key' => 'staff-return-1',
            ])
            ->assertForbidden();

        $this->assertSame(
            '8.0000',
            StockLevel::query()->where('product_variant_id', $variant->id)->firstOrFail()->on_hand_kg,
        );
    }

    public function test_customer_cannot_record_a_physical_return(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->dispatch($this->place($customer, $variant->id, 2), $admin);

        $this->actingAs($customer)
            ->post(route('admin.orders.return', $order), [
                'returns' => [$order->items()->firstOrFail()->id => '1'],
                'reason' => 'যাচাইকৃত ফেরত',
                'operation_key' => 'customer-return-1',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_record_a_verified_return_for_a_dispatched_order(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->admin()->create();
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->dispatch($this->place($customer, $variant->id, 2), $admin);

        $this->assertSame(
            '8.0000',
            StockLevel::query()->where('product_variant_id', $variant->id)->firstOrFail()->on_hand_kg,
        );

        $item = $order->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.orders.return', $order), [
                'returns' => [$item->id => '1'],
                'reason' => 'গ্রাহক পণ্য ফেরত দিয়েছেন',
                'operation_key' => 'admin-return-1',
            ])
            ->assertRedirect();

        $this->assertSame(
            '9.0000',
            StockLevel::query()->where('product_variant_id', $variant->id)->firstOrFail()->on_hand_kg,
        );

        $this->assertDatabaseHas('order_returns', [
            'order_item_id' => $item->id,
            'quantity_kg' => '1.0000',
        ]);
        $this->assertDatabaseHas('order_return_batches', [
            'order_id' => $order->id,
            'operation_key' => 'admin-return-1',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_suspended_accounts_lose_access_to_order_pages(): void
    {
        $customer = User::factory()->customer()->suspended()->create();

        $this->actingAs($customer)
            ->get(route('orders.index'))
            ->assertRedirect(route('login'));
    }
}
