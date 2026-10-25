<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderReturnBatch;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use App\Support\Kilograms;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\RunsConcurrentWorkers;
use Tests\TestCase;

/**
 * Genuine parallel verification against the isolated MySQL instance.
 *
 * Each operation runs in its own PHP process with its own PDO connection.
 * Workers announce readiness and wait for a shared barrier file, so their
 * transactions overlap instead of merely running one after another.
 *
 * Run with:
 *   RUN_MYSQL_CONCURRENCY=1 DB_CONNECTION=mysql DB_DATABASE=... php artisan test --testsuite=Feature
 *
 * These tests are skipped outside MySQL, because SQLite cannot provide the
 * row-locking semantics being verified.
 */
class ReturnConcurrencyTest extends TestCase
{
    use BuildsStockedCatalog, RunsConcurrentWorkers;

    private bool $concurrencyEnabled = false;

    /** @var list<int> */
    private array $variantIds = [];

    /** @var list<int> */
    private array $productIds = [];

    /** @var list<int> */
    private array $categoryIds = [];

    /** @var list<int> */
    private array $userIds = [];

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Real concurrency verification requires the MySQL connection.');
        }

        if ((string) env('RUN_MYSQL_CONCURRENCY', '0') !== '1') {
            $this->markTestSkipped('Set RUN_MYSQL_CONCURRENCY=1 to run MySQL concurrency tests.');
        }

        $database = (string) config('database.connections.mysql.database');

        if (! str_contains($database, 'test')) {
            $this->markTestSkipped('Refusing to run concurrency tests outside a disposable *_test database.');
        }

        $this->concurrencyEnabled = true;
        $this->orders = $this->app->make(OrderService::class);
    }

    protected function tearDown(): void
    {
        if ($this->concurrencyEnabled) {
            $this->removeTestData();
        }

        $this->removeConcurrencyDirectories();

        parent::tearDown();
    }

    private function makeTrackedVariant(
        float $weightKg = 1.0,
        float $onHandKg = 10.0,
        string $unit = ProductVariant::UNIT_KG,
    ): ProductVariant {
        $variant = $this->makeVariant(
            weightKg: $weightKg,
            unit: $unit,
            onHandKg: $onHandKg,
        );

        $this->variantIds[] = $variant->id;
        $this->productIds[] = $variant->product_id;
        $this->categoryIds[] = $variant->product->category_id;

        return $variant;
    }

    private function makeCustomer(): User
    {
        $customer = User::factory()->customer()->create();
        $this->userIds[] = $customer->id;

        return $customer;
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->userIds[] = $admin->id;

        return $admin;
    }

    private function place(
        User $customer,
        int $variantId,
        int $quantity,
        ?string $key = null,
    ): Order {
        return $this->orders->place(
            customer: $customer,
            items: [['variant_id' => $variantId, 'quantity' => $quantity]],
            deliveryAddress: 'কনকারেন্সি টেস্ট ঠিকানা, চট্টগ্রাম',
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

    private function level(int $variantId): StockLevel
    {
        return StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->firstOrFail();
    }

    private function assertOneSuccess(array $results): void
    {
        $successes = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['ok'] ?? false) === true,
        ));

        $this->assertCount(1, $successes, 'Exactly one competing transaction should succeed.');

        $failures = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['ok'] ?? false) === false,
        ));

        $this->assertCount(1, $failures);
        $this->assertSame(ValidationException::class, $failures[0]['class'] ?? null);
    }

    public function test_two_concurrent_checkouts_cannot_reserve_the_same_limited_stock(): void
    {
        $variant = $this->makeTrackedVariant(onHandKg: 10);
        $alice = $this->makeCustomer();
        $bob = $this->makeCustomer();

        $results = $this->runWorkers([
            [
                'action' => 'place',
                'customer_id' => $alice->id,
                'variant_id' => $variant->id,
                'quantity' => 8,
                'idempotency_key' => 'checkout-race-alice',
                'lat' => DeliveryFeeService::SHOP_LAT,
                'lng' => DeliveryFeeService::SHOP_LNG,
            ],
            [
                'action' => 'place',
                'customer_id' => $bob->id,
                'variant_id' => $variant->id,
                'quantity' => 8,
                'idempotency_key' => 'checkout-race-bob',
                'lat' => DeliveryFeeService::SHOP_LAT,
                'lng' => DeliveryFeeService::SHOP_LNG,
            ],
        ]);

        $this->assertOneSuccess($results);

        $orders = Order::query()
            ->whereIn('user_id', [$alice->id, $bob->id])
            ->get();

        $this->assertCount(1, $orders);
        $this->assertSame(8, $orders[0]->items()->firstOrFail()->quantity);

        $level = $this->level($variant->id);

        $this->assertSame('10.0000', $level->on_hand_kg);
        $this->assertSame('8.0000', $level->reserved_kg);
        $this->assertSame('2.0000', $level->available_kg);

        $this->assertSame(1, StockReservation::query()
            ->where('product_variant_id', $variant->id)
            ->where('status', StockReservation::STATUS_PENDING)
            ->count());

        $this->assertSame(1, StockMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('movement_type', StockMovement::TYPE_RESERVE)
            ->count());
    }

    public function test_two_concurrent_returns_cannot_exceed_the_remaining_dispatched_quantity(): void
    {
        $variant = $this->makeTrackedVariant(onHandKg: 10);
        $customer = $this->makeCustomer();
        $admin = $this->makeAdmin();

        $order = $this->dispatch($this->place($customer, $variant->id, 2), $admin);
        $item = $order->items()->firstOrFail();

        $this->assertSame('8.0000', $this->level($variant->id)->on_hand_kg);

        $results = $this->runWorkers([
            [
                'action' => 'return',
                'order_id' => $order->id,
                'actor_user_id' => $admin->id,
                'lines' => [['order_item_id' => $item->id, 'quantity_kg' => '1.5']],
                'operation_key' => 'concurrent-return-a',
                'reason' => 'concurrent return A',
            ],
            [
                'action' => 'return',
                'order_id' => $order->id,
                'actor_user_id' => $admin->id,
                'lines' => [['order_item_id' => $item->id, 'quantity_kg' => '1.5']],
                'operation_key' => 'concurrent-return-b',
                'reason' => 'concurrent return B',
            ],
        ]);

        $this->assertOneSuccess($results);

        $returned = OrderReturn::query()->where('order_item_id', $item->id)->sum('quantity_kg');

        $this->assertSame('9.5000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(Kilograms::toUnits('1.5'), Kilograms::toUnits($returned));
        $this->assertSame(1, OrderReturnBatch::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, OrderReturn::query()->where('order_item_id', $item->id)->count());
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());
        $this->assertSame(Kilograms::toUnits('0.5'), $item->fresh()->remainingReturnableUnits());
    }

    public function test_concurrent_duplicate_operation_keys_restock_exactly_once(): void
    {
        $variant = $this->makeTrackedVariant(onHandKg: 10);
        $customer = $this->makeCustomer();
        $admin = $this->makeAdmin();

        $order = $this->dispatch($this->place($customer, $variant->id, 2), $admin);
        $item = $order->items()->firstOrFail();

        $payload = [
            'action' => 'return',
            'order_id' => $order->id,
            'actor_user_id' => $admin->id,
            'lines' => [['order_item_id' => $item->id, 'quantity_kg' => '1']],
            'operation_key' => 'duplicate-concurrent-key',
            'reason' => 'same key from two requests',
        ];

        $results = $this->runWorkers([$payload, $payload]);

        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame(
            $results[0]['result']['batch_id'],
            $results[1]['result']['batch_id'],
        );
        $this->assertSame(
            $results[0]['result']['reference'],
            $results[1]['result']['reference'],
        );

        $this->assertSame('9.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(1, OrderReturnBatch::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, OrderReturn::query()->where('order_item_id', $item->id)->count());
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());
    }

    public function test_expiry_racing_with_acceptance_has_exactly_one_winner(): void
    {
        $variant = $this->makeTrackedVariant(onHandKg: 10);
        $customer = $this->makeCustomer();
        $admin = $this->makeAdmin();

        $order = $this->place($customer, $variant->id, 2, 'expiry-accept-race');

        StockReservation::query()
            ->where('order_id', $order->id)
            ->update(['expires_at' => now()->subMinute()]);

        $results = $this->runWorkers([
            [
                'action' => 'expire',
                'order_id' => $order->id,
            ],
            [
                'action' => 'accept',
                'order_id' => $order->id,
                'actor_user_id' => $admin->id,
            ],
        ]);

        $order = $order->fresh();
        $reservation = StockReservation::query()->where('order_id', $order->id)->firstOrFail();
        $level = $this->level($variant->id);

        $releaseMovements = StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count();

        $this->assertSame('10.0000', $level->on_hand_kg);

        if ($order->status === Order::STATUS_CANCELLED) {
            $this->assertSame('expiry', $order->cancelled_by);
            $this->assertTrue($results[0]['ok']);
            $this->assertTrue($results[0]['result']['expired']);
            $this->assertFalse($results[1]['ok']);
            $this->assertSame(StockReservation::STATUS_RELEASED, $reservation->status);
            $this->assertSame(StockReservation::RELEASE_EXPIRED, $reservation->release_reason);
            $this->assertSame('0.0000', $level->reserved_kg);
            $this->assertSame(1, $releaseMovements);
        } else {
            $this->assertSame(Order::STATUS_ACCEPTED, $order->status);
            $this->assertTrue($results[1]['ok']);
            $this->assertFalse($results[0]['result']['expired']);
            $this->assertSame(StockReservation::STATUS_PENDING, $reservation->status);
            $this->assertNull($reservation->expires_at);
            $this->assertSame('2.0000', $level->reserved_kg);
            $this->assertSame(0, $releaseMovements);
        }
    }

    public function test_expiry_racing_with_customer_cancellation_has_exactly_one_winner(): void
    {
        $variant = $this->makeTrackedVariant(onHandKg: 10);
        $customer = $this->makeCustomer();

        $order = $this->place($customer, $variant->id, 2, 'expiry-cancel-race');

        StockReservation::query()
            ->where('order_id', $order->id)
            ->update(['expires_at' => now()->subMinute()]);

        $results = $this->runWorkers([
            [
                'action' => 'expire',
                'order_id' => $order->id,
            ],
            [
                'action' => 'cancel',
                'order_id' => $order->id,
                'customer_id' => $customer->id,
            ],
        ]);

        $order = $order->fresh();
        $reservation = StockReservation::query()->where('order_id', $order->id)->firstOrFail();
        $level = $this->level($variant->id);

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame('0.0000', $level->reserved_kg);
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RELEASE)
            ->count());

        if ($order->cancelled_by === 'expiry') {
            $this->assertTrue($results[0]['ok']);
            $this->assertTrue($results[0]['result']['expired']);
            $this->assertFalse($results[1]['ok']);
            $this->assertSame(StockReservation::RELEASE_EXPIRED, $reservation->release_reason);
        } else {
            $this->assertSame('customer', $order->cancelled_by);
            $this->assertTrue($results[1]['ok']);
            $this->assertFalse($results[0]['result']['expired']);
            $this->assertSame(StockReservation::RELEASE_CUSTOMER, $reservation->release_reason);
        }

        $this->assertSame(1, $order->statusEvents()
            ->where('to_status', Order::STATUS_CANCELLED)
            ->count());
    }

    private function removeTestData(): void
    {
        $variantIds = array_values(array_unique($this->variantIds));
        $userIds = array_values(array_unique($this->userIds));
        $productIds = array_values(array_unique($this->productIds));
        $categoryIds = array_values(array_unique($this->categoryIds));

        $orderIds = DB::table('orders')
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->all();

        DB::table('stock_movements')->whereIn('product_variant_id', $variantIds)->delete();
        DB::table('order_returns')->whereIn('order_id', $orderIds)->delete();
        DB::table('order_return_batches')->whereIn('order_id', $orderIds)->delete();
        DB::table('stock_reservations')->whereIn('order_id', $orderIds)->delete();
        DB::table('order_status_events')->whereIn('order_id', $orderIds)->delete();
        DB::table('invoices')->whereIn('order_id', $orderIds)->delete();
        DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
        DB::table('orders')->whereIn('id', $orderIds)->delete();
        DB::table('stock_levels')->whereIn('product_variant_id', $variantIds)->delete();
        DB::table('product_media')->whereIn('product_id', $productIds)->delete();
        DB::table('product_variants')->whereIn('id', $variantIds)->delete();
        DB::table('products')->whereIn('id', $productIds)->delete();
        DB::table('categories')->whereIn('id', $categoryIds)->delete();
        DB::table('user_permission_overrides')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }
}
