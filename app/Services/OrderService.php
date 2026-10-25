<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Transactional order placement and lifecycle.
 *
 * Stock semantics:
 *  - reserve: reserved_kg += kg, physical unchanged
 *  - dispatch: on_hand_kg -= kg AND reserved_kg -= kg (physical leaves the shop)
 *  - release: reserved_kg -= kg, physical unchanged
 *  - return:  on_hand_kg += kg (only after a verified physical return)
 *
 * Delivery confirmation never touches stock again, so the same goods can
 * never be deducted twice.
 */
class OrderService
{
    public function __construct(
        private readonly PricingService $pricing,
    ) {
    }

    /**
     * @param  array<int, array{variant_id:int|string, quantity:int|string}>  $items
     */
    public function place(
        User $customer,
        array $items,
        string $deliveryAddress,
        float $lat,
        float $lng,
        ?string $customerNote = null,
        ?string $idempotencyKey = null,
    ): Order {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = Order::query()
                ->where('user_id', $customer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use (
                $customer,
                $items,
                $deliveryAddress,
                $lat,
                $lng,
                $customerNote,
                $idempotencyKey,
            ): Order {
                $quote = $this->pricing->quote($items, $lat, $lng);

                $variantIds = collect($quote['lines'])
                    ->pluck('variant_id')
                    ->sort()
                    ->values()
                    ->all();

                // Lock every affected stock row in a deterministic order to
                // avoid deadlocks between concurrent checkouts.
                $levels = [];

                foreach ($variantIds as $variantId) {
                    $levels[$variantId] = $this->lockStockLevel((int) $variantId);
                }

                $this->assertStockAvailable($quote['lines'], $levels);

                $order = Order::create([
                    'code' => $this->generateOrderCode(),
                    'user_id' => $customer->id,
                    'status' => Order::STATUS_PENDING,
                    'payment_mode' => Order::PAYMENT_COD,
                    'subtotal' => $quote['subtotal'],
                    'delivery_fee' => $quote['delivery_fee'],
                    'total' => $quote['total'],
                    'delivery_address' => $deliveryAddress,
                    'delivery_lat' => $lat,
                    'delivery_lng' => $lng,
                    'distance_km' => $quote['distance_km'],
                    'customer_note' => $customerNote,
                    'idempotency_key' => $idempotencyKey ?: null,
                ]);

                foreach ($quote['lines'] as $line) {
                    $order->items()->create([
                        'product_id' => $line['product_id'],
                        'product_variant_id' => $line['variant_id'],
                        'product_title' => $line['product_title'],
                        'variant_label' => $line['variant_label'],
                        'unit' => $line['unit'],
                        'weight_kg' => $line['weight_kg'],
                        'unit_price' => $line['unit_price'],
                        'quantity' => $line['quantity'],
                        'line_total' => $line['line_total'],
                    ]);

                    $this->reserveLine(
                        $order,
                        (int) $line['variant_id'],
                        (float) $line['requested_kg'],
                        $customer,
                    );
                }

                $this->appendStatusEvent($order, null, Order::STATUS_PENDING, $customer, 'অর্ডার তৈরি হয়েছে।');

                $this->issueInvoice($order);

                return $order->fresh(['items', 'statusEvents', 'invoice']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Two identical checkout submissions raced. Return the winner.
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = Order::query()
                    ->where('user_id', $customer->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    public function cancelByCustomer(Order $order, User $actor): Order
    {
        return DB::transaction(function () use ($order, $actor): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($locked->status, [Order::STATUS_PENDING, Order::STATUS_ACCEPTED], true)
                || $locked->isDispatched()) {
                throw ValidationException::withMessages([
                    'order' => 'এই অর্ডারটি এখন বাতিল করা যাবে না।',
                ]);
            }

            $this->releaseReservations($locked, $actor, 'গ্রাহক অর্ডার বাতিল করেছেন।');

            $previous = $locked->status;

            $locked->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => 'customer',
            ]);

            $this->appendStatusEvent(
                $locked,
                $previous,
                Order::STATUS_CANCELLED,
                $actor,
                'গ্রাহক অর্ডার বাতিল করেছেন।',
            );

            return $locked->fresh(['items', 'statusEvents', 'invoice']);
        });
    }

    public function cancelByShop(Order $order, User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $actor, $note): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($locked->status, [Order::STATUS_PENDING, Order::STATUS_ACCEPTED, Order::STATUS_IN_TRANSIT], true)) {
                throw ValidationException::withMessages([
                    'order' => 'এই অর্ডারটি এখন বাতিল করা যাবে না।',
                ]);
            }

            $previous = $locked->status;

            // If goods already left the shop, do not silently restock them.
            // A verified physical return must be recorded separately.
            if (! $locked->isDispatched()) {
                $this->releaseReservations($locked, $actor, $note ?: 'দোকান অর্ডার বাতিল করেছে।');
            }

            $locked->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => 'shop',
            ]);

            $this->appendStatusEvent($locked, $previous, Order::STATUS_CANCELLED, $actor, $note);

            return $locked->fresh(['items', 'statusEvents', 'invoice']);
        });
    }

    public function transition(Order $order, string $toStatus, User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $toStatus, $actor, $note): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->canTransitionTo($toStatus)) {
                throw ValidationException::withMessages([
                    'order' => "এই অর্ডারটি {$locked->status} থেকে {$toStatus} অবস্থায় নেওয়া যাবে না।",
                ]);
            }

            $previous = $locked->status;

            switch ($toStatus) {
                case Order::STATUS_IN_TRANSIT:
                    $this->dispatchReservations($locked, $actor);
                    $locked->dispatched_at = now();
                    break;

                case Order::STATUS_DELIVERED:
                    $locked->delivered_at = now();
                    break;
            }

            $locked->status = $toStatus;
            $locked->save();

            $this->appendStatusEvent($locked, $previous, $toStatus, $actor, $note);

            return $locked->fresh(['items', 'statusEvents', 'invoice']);
        });
    }

    /**
     * Record an approved physical return. Restocks saleable goods exactly once
     * per operation key.
     *
     * @param  array<int, float>  $kilogramsByVariant
     */
    public function recordReturn(Order $order, User $actor, array $kilogramsByVariant, string $operationKey): void
    {
        DB::transaction(function () use ($order, $actor, $kilogramsByVariant, $operationKey): void {
            // Serialise returns for the same order so a duplicate submission
            // cannot restock before the idempotency key is visible.
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($lockedOrder->status, [Order::STATUS_IN_TRANSIT, Order::STATUS_DELIVERED], true)) {
                throw ValidationException::withMessages([
                    'order' => 'পণ্য পাঠানোর আগে ফেরত স্টকে যোগ করা যাবে না।',
                ]);
            }

            foreach ($kilogramsByVariant as $variantId => $kilograms) {
                $variantId = (int) $variantId;
                $kilograms = round((float) $kilograms, 4);

                if ($kilograms <= 0) {
                    continue;
                }

                $belongsToOrder = $lockedOrder->items()
                    ->where('product_variant_id', $variantId)
                    ->exists();

                if (! $belongsToOrder) {
                    throw ValidationException::withMessages([
                        'product_variant_id' => 'এই ভ্যারিয়েন্টটি এই অর্ডারের অংশ নয়।',
                    ]);
                }

                $movementKey = $operationKey.':variant:'.$variantId;

                // Exactly-once restock: a retried return with the same
                // operation key must not add the same goods twice.
                if (StockMovement::query()->where('operation_key', $movementKey)->exists()) {
                    continue;
                }

                $level = $this->lockStockLevel($variantId);

                $level->on_hand_kg = round((float) $level->on_hand_kg + $kilograms, 4);
                $level->syncAvailable();
                $level->save();

                $this->recordMovement(
                    variantId: $variantId,
                    type: StockMovement::TYPE_RETURN,
                    bucket: StockMovement::BUCKET_PHYSICAL,
                    direction: StockMovement::DIRECTION_IN,
                    kilograms: $kilograms,
                    operationKey: $movementKey,
                    order: $lockedOrder,
                    actor: $actor,
                    note: 'যাচাইকৃত ফেরত পণ্য পুনরায় স্টকে যোগ হয়েছে।',
                );
            }
        });
    }

    /**
     * @param  array<int, array{variant_id:int, requested_kg:float}>  $lines
     * @param  array<int, StockLevel>  $levels
     */
    private function assertStockAvailable(array $lines, array $levels): void
    {
        foreach ($lines as $line) {
            $level = $levels[$line['variant_id']] ?? null;
            $available = $level?->available() ?? 0.0;

            if ($level === null || $available + 0.0001 < $line['requested_kg']) {
                $variant = ProductVariant::find($line['variant_id']);
                $label = $variant?->displayLabel() ?? ('#'.$line['variant_id']);

                throw ValidationException::withMessages([
                    'items' => "{$label} — পর্যাপ্ত স্টক নেই।",
                ]);
            }
        }
    }

    private function reserveLine(Order $order, int $variantId, float $kilograms, User $actor): void
    {
        $level = $this->lockStockLevel($variantId);

        if ($level->available() + 0.0001 < $kilograms) {
            throw ValidationException::withMessages([
                'items' => 'স্টক রিজার্ভ করা যায়নি — অন্য একটি চেকআউট একই স্টক নিয়ে নিয়েছে।',
            ]);
        }

        $level->reserved_kg = round((float) $level->reserved_kg + $kilograms, 4);
        $level->syncAvailable();
        $level->save();

        StockReservation::create([
            'product_variant_id' => $variantId,
            'order_id' => $order->id,
            'reserved_kg' => $kilograms,
            'reference_type' => 'order',
            'reference_id' => (string) $order->id,
            'status' => StockReservation::STATUS_PENDING,
            'actor_user_id' => $actor->id,
        ]);

        $this->recordMovement(
            variantId: $variantId,
            type: StockMovement::TYPE_RESERVE,
            bucket: StockMovement::BUCKET_RESERVED,
            direction: StockMovement::DIRECTION_IN,
            kilograms: $kilograms,
            operationKey: 'reserve:order:'.$order->id.':variant:'.$variantId,
            order: $order,
            actor: $actor,
            note: 'অর্ডারের জন্য স্টক রিজার্ভ।',
        );
    }

    private function releaseReservations(Order $order, User $actor, ?string $note): void
    {
        $reservations = StockReservation::query()
            ->where('order_id', $order->id)
            ->where('status', StockReservation::STATUS_PENDING)
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $variantId = (int) $reservation->product_variant_id;
            $kilograms = (float) $reservation->reserved_kg;

            $level = $this->lockStockLevel($variantId);
            $level->reserved_kg = max(0, round((float) $level->reserved_kg - $kilograms, 4));
            $level->syncAvailable();
            $level->save();

            $reservation->status = StockReservation::STATUS_RELEASED;
            $reservation->released_at = now();
            $reservation->save();

            $this->recordMovement(
                variantId: $variantId,
                type: StockMovement::TYPE_RELEASE,
                bucket: StockMovement::BUCKET_RESERVED,
                direction: StockMovement::DIRECTION_OUT,
                kilograms: $kilograms,
                operationKey: 'release:order:'.$order->id.':variant:'.$variantId,
                order: $order,
                actor: $actor,
                note: $note,
            );
        }
    }

    private function dispatchReservations(Order $order, User $actor): void
    {
        $reservations = StockReservation::query()
            ->where('order_id', $order->id)
            ->where('status', StockReservation::STATUS_PENDING)
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $variantId = (int) $reservation->product_variant_id;
            $kilograms = (float) $reservation->reserved_kg;

            $level = $this->lockStockLevel($variantId);
            $level->on_hand_kg = max(0, round((float) $level->on_hand_kg - $kilograms, 4));
            $level->reserved_kg = max(0, round((float) $level->reserved_kg - $kilograms, 4));
            $level->syncAvailable();
            $level->save();

            $reservation->status = StockReservation::STATUS_FULFILLED;
            $reservation->fulfilled_at = now();
            $reservation->save();

            $this->recordMovement(
                variantId: $variantId,
                type: StockMovement::TYPE_DISPATCH,
                bucket: StockMovement::BUCKET_PHYSICAL,
                direction: StockMovement::DIRECTION_OUT,
                kilograms: $kilograms,
                operationKey: 'dispatch-physical:order:'.$order->id.':variant:'.$variantId,
                order: $order,
                actor: $actor,
                note: 'অর্ডার ডেলিভারির জন্য পাঠানো হয়েছে।',
            );

            $this->recordMovement(
                variantId: $variantId,
                type: StockMovement::TYPE_DISPATCH,
                bucket: StockMovement::BUCKET_RESERVED,
                direction: StockMovement::DIRECTION_OUT,
                kilograms: $kilograms,
                operationKey: 'dispatch-reserved:order:'.$order->id.':variant:'.$variantId,
                order: $order,
                actor: $actor,
                note: 'রিজার্ভেড স্টক ছাড়া হয়েছে।',
            );
        }
    }

    private function appendStatusEvent(
        Order $order,
        ?string $from,
        string $to,
        ?User $actor,
        ?string $note,
    ): void {
        OrderStatusEvent::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'note' => $note,
        ]);
    }

    private function issueInvoice(Order $order): Invoice
    {
        return Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => InvoiceSequence::nextNumber(),
            'subtotal' => $order->subtotal,
            'delivery_fee' => $order->delivery_fee,
            'total' => $order->total,
            'issued_at' => now(),
        ]);
    }

    private function lockStockLevel(int $variantId): StockLevel
    {
        // Ensure a row exists, then lock it. The unique constraint on
        // product_variant_id makes the insert safe under concurrency.
        DB::table('stock_levels')->insertOrIgnore([
            'product_variant_id' => $variantId,
            'on_hand_kg' => 0,
            'reserved_kg' => 0,
            'available_kg' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function recordMovement(
        int $variantId,
        string $type,
        string $bucket,
        string $direction,
        float $kilograms,
        string $operationKey,
        Order $order,
        ?User $actor,
        ?string $note = null,
    ): void {
        StockMovement::query()->firstOrCreate(
            ['operation_key' => $operationKey],
            [
                'product_variant_id' => $variantId,
                'movement_type' => $type,
                'stock_bucket' => $bucket,
                'direction' => $direction,
                'quantity_kg' => $kilograms,
                'reference_type' => 'order',
                'reference_id' => (string) $order->id,
                'note' => $note,
                'actor_user_id' => $actor?->id,
            ],
        );
    }

    private function generateOrderCode(): string
    {
        do {
            $code = 'CBP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('code', $code)->exists());

        return $code;
    }
}
