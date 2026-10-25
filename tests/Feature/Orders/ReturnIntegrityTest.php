<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnBatch;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReturnIntegrityTest extends TestCase
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

    /**
     * @param  array<int, array{variant_id:int, quantity:int}>  $items
     */
    private function place(array $items, ?string $key = null): Order
    {
        return $this->orders->place(
            customer: $this->customer,
            items: $items,
            deliveryAddress: '১২৩ টেস্ট রোড, চট্টগ্রাম',
            lat: DeliveryFeeService::SHOP_LAT,
            lng: DeliveryFeeService::SHOP_LNG,
            idempotencyKey: $key,
        );
    }

    private function placeOne(int $variantId, int $quantity = 2, ?string $key = null): Order
    {
        return $this->place([['variant_id' => $variantId, 'quantity' => $quantity]], $key);
    }

    private function dispatch(Order $order): Order
    {
        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);
        $this->orders->transition($order->fresh(), Order::STATUS_IN_TRANSIT, $this->admin);

        return $order->fresh();
    }

    private function item(Order $order): OrderItem
    {
        return $order->items()->firstOrFail();
    }

    private function level(int $variantId): StockLevel
    {
        return StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->firstOrFail();
    }

    private function assertRejected(callable $callback, ?string $errorKey = null): void
    {
        try {
            $callback();
            $this->fail('The return should have been rejected.');
        } catch (ValidationException $exception) {
            if ($errorKey !== null) {
                $this->assertArrayHasKey($errorKey, $exception->errors());
            } else {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_partial_return_restocks_and_records_an_audit_trail(): void
    {
        $variant = $this->makeVariant(weightKg: 1.0, onHandKg: 10);
        $order = $this->dispatch($this->placeOne($variant->id, 3));
        $item = $this->item($order);

        $this->assertSame('7.0000', $this->level($variant->id)->on_hand_kg);

        $batch = $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '1.25']],
            'return-audit-1',
            'গ্রাহক একটি প্যাকেট ফেরত দিয়েছেন',
        );

        $this->assertMatchesRegularExpression('/^RET-\d{8}-[A-Z0-9]{6}$/', $batch->reference);
        $this->assertSame($order->id, $batch->order_id);
        $this->assertSame($this->admin->id, $batch->actor_user_id);
        $this->assertSame('গ্রাহক একটি প্যাকেট ফেরত দিয়েছেন', $batch->reason);
        $this->assertNotNull($batch->received_at);
        $this->assertNotNull($batch->approved_at);
        $this->assertTrue($batch->isApproved());

        $this->assertSame('8.2500', $this->level($variant->id)->on_hand_kg);
        $this->assertSame('8.2500', $this->level($variant->id)->available_kg);

        $this->assertDatabaseHas('order_returns', [
            'order_return_batch_id' => $batch->id,
            'order_item_id' => $item->id,
            'product_variant_id' => $variant->id,
            'quantity_kg' => '1.2500',
        ]);

        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->where('stock_bucket', StockMovement::BUCKET_PHYSICAL)
            ->where('direction', StockMovement::DIRECTION_IN)
            ->count());

        $this->assertSame('1.2500', StockMovement::query()
            ->where('operation_key', 'return:batch:'.$batch->id.':item:'.$item->id)
            ->value('quantity_kg'));
    }

    public function test_full_return_is_allowed_but_one_ten_thousandth_over_is_rejected(): void
    {
        $variant = $this->makeVariant(weightKg: 1.0, onHandKg: 20);
        $order = $this->dispatch($this->placeOne($variant->id, 2));
        $item = $this->item($order);

        $batch = $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '2']],
            'full-return-1',
        );

        $this->assertSame('20.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame('0.0000', $this->level($variant->id)->reserved_kg);
        $this->assertSame(0, $item->fresh()->remainingReturnableUnits());
        $this->assertSame(1, $batch->returns()->count());

        $this->assertRejected(fn () => $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '0.0001']],
            'full-return-2',
        ), 'returns');

        $this->assertSame('20.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(1, OrderReturnBatch::query()->where('order_id', $order->id)->count());
    }

    public function test_multiple_partial_returns_cannot_exceed_the_dispatched_weight(): void
    {
        $variant = $this->makeVariant(weightKg: 1.0, onHandKg: 20);
        $order = $this->dispatch($this->placeOne($variant->id, 2));
        $item = $this->item($order);

        $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '1.2']],
            'partial-1',
        );

        $this->assertRejected(fn () => $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '0.9']],
            'partial-2',
        ), 'returns');

        $this->assertSame('19.2000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(12000, \App\Support\Kilograms::toUnits(
            $item->fresh()->returns()->sum('quantity_kg'),
        ));
        $this->assertSame(1, OrderReturnBatch::query()->where('order_id', $order->id)->count());
    }

    public function test_duplicate_operation_key_returns_the_original_batch_without_restocking(): void
    {
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->dispatch($this->placeOne($variant->id, 2));
        $item = $this->item($order);

        $first = $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '1']],
            'duplicate-key',
        );

        $second = $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '1']],
            'duplicate-key',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->reference, $second->reference);
        $this->assertSame('9.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(1, OrderReturnBatch::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, StockMovement::query()
            ->where('reference_id', (string) $order->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());
    }

    public function test_return_before_dispatch_is_rejected_at_pending_and_accepted_states(): void
    {
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->placeOne($variant->id, 2);
        $item = $this->item($order);

        $this->assertRejected(fn () => $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '1']],
            'before-dispatch-pending',
        ), 'order');

        $this->orders->transition($order, Order::STATUS_ACCEPTED, $this->admin);

        $this->assertRejected(fn () => $this->orders->recordReturn(
            $order->fresh(),
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '1']],
            'before-dispatch-accepted',
        ), 'order');

        $this->assertSame('10.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(0, OrderReturnBatch::query()->count());
        $this->assertSame(0, StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());
    }

    public function test_item_from_an_unrelated_order_is_rejected(): void
    {
        $first = $this->makeVariant(onHandKg: 10);
        $second = $this->makeVariant(onHandKg: 10);
        $order = $this->dispatch($this->placeOne($first->id, 2));
        $otherOrder = $this->placeOne($second->id, 2);
        $otherItem = $this->item($otherOrder);

        $this->assertRejected(fn () => $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $otherItem->id, 'quantity_kg' => '1']],
            'unrelated-item',
        ), 'returns');

        $this->assertSame('8.0000', $this->level($first->id)->on_hand_kg);
        $this->assertSame('10.0000', $this->level($second->id)->on_hand_kg);
    }

    public function test_unauthorized_staff_cannot_restock_even_with_a_valid_payload(): void
    {
        $staff = User::factory()->staff()->create();
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->dispatch($this->placeOne($variant->id, 2));
        $item = $this->item($order);

        $this->assertFalse($staff->hasPermission('inventory'));

        $this->actingAs($staff)
            ->post(route('admin.orders.return', $order), [
                'returns' => [$item->id => '1'],
                'reason' => 'unauthorised attempt',
                'operation_key' => 'unauthorised-return',
            ])
            ->assertForbidden();

        $this->assertSame('8.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(0, OrderReturnBatch::query()->count());
        $this->assertSame(0, StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());
    }

    public function test_kg_and_sack_variants_use_the_historical_weight_snapshot(): void
    {
        $kg = $this->makeVariant(weightKg: 1.0, onHandKg: 10);
        $sack25 = $this->makeVariant(
            weightKg: 25.0,
            unit: ProductVariant::UNIT_SACK_25KG,
            onHandKg: 100,
        );
        $sack50 = $this->makeVariant(
            weightKg: 50.0,
            unit: ProductVariant::UNIT_SACK_50KG,
            onHandKg: 100,
        );

        $order = $this->place([
            ['variant_id' => $kg->id, 'quantity' => 2],
            ['variant_id' => $sack25->id, 'quantity' => 1],
            ['variant_id' => $sack50->id, 'quantity' => 1],
        ]);

        $order = $this->dispatch($order);
        $byVariant = $order->items()->get()->keyBy('product_variant_id');

        $this->assertSame('8.0000', $this->level($kg->id)->on_hand_kg);
        $this->assertSame('75.0000', $this->level($sack25->id)->on_hand_kg);
        $this->assertSame('50.0000', $this->level($sack50->id)->on_hand_kg);

        // One ten-thousandth over each dispatched weight must fail.
        $this->assertRejected(fn () => $this->orders->recordReturn(
            $order,
            $this->admin,
            [
                ['order_item_id' => $byVariant[$kg->id]->id, 'quantity_kg' => '2.0001'],
                ['order_item_id' => $byVariant[$sack25->id]->id, 'quantity_kg' => '25'],
                ['order_item_id' => $byVariant[$sack50->id]->id, 'quantity_kg' => '50'],
            ],
            'sack-over-limit',
        ), 'returns');

        $this->assertSame('8.0000', $this->level($kg->id)->on_hand_kg);
        $this->assertSame(0, OrderReturnBatch::query()->count());

        $batch = $this->orders->recordReturn(
            $order,
            $this->admin,
            [
                ['order_item_id' => $byVariant[$kg->id]->id, 'quantity_kg' => '2'],
                ['order_item_id' => $byVariant[$sack25->id]->id, 'quantity_kg' => '25'],
                ['order_item_id' => $byVariant[$sack50->id]->id, 'quantity_kg' => '50'],
            ],
            'sack-full-return',
        );

        $this->assertSame(3, $batch->returns()->count());
        $this->assertSame('10.0000', $this->level($kg->id)->on_hand_kg);
        $this->assertSame('100.0000', $this->level($sack25->id)->on_hand_kg);
        $this->assertSame('100.0000', $this->level($sack50->id)->on_hand_kg);
    }

    public function test_product_weight_changes_do_not_increase_the_returnable_limit(): void
    {
        $variant = $this->makeVariant(weightKg: 1.0, onHandKg: 10);
        $order = $this->dispatch($this->placeOne($variant->id, 2));
        $item = $this->item($order);

        $variant->update(['weight_kg' => 5.0]);

        $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '2']],
            'snapshot-full',
        );

        $this->assertRejected(fn () => $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $item->id, 'quantity_kg' => '0.0001']],
            'snapshot-over',
        ), 'returns');

        $this->assertSame('10.0000', $this->level($variant->id)->on_hand_kg);
    }

    public function test_multi_item_return_rolls_back_atomically_when_one_line_is_invalid(): void
    {
        $first = $this->makeVariant(onHandKg: 10);
        $second = $this->makeVariant(onHandKg: 10);
        $order = $this->place([
            ['variant_id' => $first->id, 'quantity' => 2],
            ['variant_id' => $second->id, 'quantity' => 2],
        ]);
        $order = $this->dispatch($order);
        $byVariant = $order->items()->get()->keyBy('product_variant_id');

        $this->assertSame('8.0000', $this->level($first->id)->on_hand_kg);
        $this->assertSame('8.0000', $this->level($second->id)->on_hand_kg);

        $this->assertRejected(fn () => $this->orders->recordReturn(
            $order,
            $this->admin,
            [
                ['order_item_id' => $byVariant[$first->id]->id, 'quantity_kg' => '1'],
                ['order_item_id' => $byVariant[$second->id]->id, 'quantity_kg' => '2.5'],
            ],
            'atomic-failure',
        ), 'returns');

        $this->assertSame('8.0000', $this->level($first->id)->on_hand_kg);
        $this->assertSame('8.0000', $this->level($second->id)->on_hand_kg);
        $this->assertSame(0, OrderReturnBatch::query()->count());
        $this->assertSame(0, \App\Models\OrderReturn::query()->count());
        $this->assertSame(0, StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->count());

        $batch = $this->orders->recordReturn(
            $order,
            $this->admin,
            [
                ['order_item_id' => $byVariant[$first->id]->id, 'quantity_kg' => '1'],
                ['order_item_id' => $byVariant[$second->id]->id, 'quantity_kg' => '2'],
            ],
            'atomic-success',
        );

        $this->assertSame(2, $batch->returns()->count());
        $this->assertSame('9.0000', $this->level($first->id)->on_hand_kg);
        $this->assertSame('10.0000', $this->level($second->id)->on_hand_kg);
    }

    public function test_zero_negative_malformed_and_excessively_precise_quantities_are_rejected(): void
    {
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->dispatch($this->placeOne($variant->id, 2));
        $item = $this->item($order);

        foreach (['0', '-0.5', 'abc', '1.00001', ''] as $index => $quantity) {
            $this->assertRejected(fn () => $this->orders->recordReturn(
                $order,
                $this->admin,
                [['order_item_id' => $item->id, 'quantity_kg' => $quantity]],
                'invalid-quantity-'.$index,
            ), 'returns');
        }

        $this->assertSame('8.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(0, OrderReturnBatch::query()->count());
    }

    public function test_http_validation_rejects_zero_quantity_before_the_service_runs(): void
    {
        $variant = $this->makeVariant(onHandKg: 10);
        $order = $this->dispatch($this->placeOne($variant->id, 2));
        $item = $this->item($order);

        $this->actingAs($this->admin)
            ->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.return', $order), [
                'returns' => [$item->id => '0'],
                'operation_key' => 'http-zero',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors('returns.'.$item->id);

        $this->assertSame('8.0000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame(0, OrderReturnBatch::query()->count());
    }

    public function test_physical_ledger_reconciles_with_on_hand_after_a_return(): void
    {
        $variant = $this->makeVariant(onHandKg: 50);
        $order = $this->dispatch($this->placeOne($variant->id, 4));

        $this->orders->recordReturn(
            $order,
            $this->admin,
            [['order_item_id' => $this->item($order)->id, 'quantity_kg' => '1.5']],
            'ledger-return',
        );

        $physicalDelta = StockMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('stock_bucket', StockMovement::BUCKET_PHYSICAL)
            ->get()
            ->reduce(
                static fn (int $carry, StockMovement $movement): int => $carry
                    + ($movement->direction === StockMovement::DIRECTION_IN
                        ? (int) round((float) $movement->quantity_kg * 10000)
                        : -(int) round((float) $movement->quantity_kg * 10000)),
                0,
            );

        $this->assertSame(50 * 10000 + $physicalDelta, $this->level($variant->id)->onHandUnits());
        $this->assertSame('47.5000', $this->level($variant->id)->on_hand_kg);
        $this->assertSame('47.5000', $this->level($variant->id)->available_kg);
    }
}
