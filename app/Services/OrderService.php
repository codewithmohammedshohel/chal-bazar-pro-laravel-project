<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnBatch;
use App\Models\OrderStatusEvent;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Kilograms;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Transactional order placement and lifecycle.
 *
 * Stock semantics:
 *  - reserve: reserved_kg += kg, physical unchanged
 *  - dispatch: on_hand_kg -= kg AND reserved_kg -= kg (physical leaves the shop)
 *  - release: reserved_kg -= kg, physical unchanged
 *  - return:  on_hand_kg += kg (only after a verified, approved physical return)
 *
 * Delivery confirmation never touches stock again, so the same goods can
 * never be deducted twice. All kilogram arithmetic uses integer 1/10,000 kg
 * units so limits cannot drift at decimal boundaries.
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

            $note = 'গ্রাহক অর্ডার বাতিল করেছেন।';

            $this->releaseReservations(
                $locked,
                $actor,
                $note,
                StockReservation::RELEASE_CUSTOMER,
            );

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
                $note,
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
                $this->releaseReservations(
                    $locked,
                    $actor,
                    $note ?: 'দোকান অর্ডার বাতিল করেছে।',
                    StockReservation::RELEASE_SHOP,
                );
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
                case Order::STATUS_ACCEPTED:
                    // Once accepted, the pending reservation is no longer
                    // eligible for automatic expiry.
                    $this->clearReservationExpiry($locked);
                    break;

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
     * Record one approved physical return submission.
     *
     * Every line points at a real order item. The cumulative returned weight
     * is compared against the item's historical dispatched weight
     * (ordered quantity × weight snapshot). The order row and all affected
     * order-item rows are locked, so different operation keys cannot race
     * past the limit, while the batch operation key makes retries idempotent.
     *
     * A return is accepted for any order whose goods actually left the shop,
     * including an order the shop later cancelled while it was in transit.
     * Cancelled orders that were never dispatched are rejected, because no
     * physical stock ever left the shop for them.
     *
     * @param  array<int, array{order_item_id:int|string, quantity_kg:int|float|string}>  $lines
     */
    public function recordReturn(
        Order $order,
        User $actor,
        array $lines,
        string $operationKey,
        string $reason,
    ): OrderReturnBatch {
        $operationKey = trim($operationKey);
        $reason = trim($reason);

        if ($operationKey === '' || mb_strlen($operationKey) > 80) {
            throw ValidationException::withMessages([
                'operation_key' => 'ফেরতের রেফারেন্স ১ থেকে ৮০ অক্ষরের হতে হবে।',
            ]);
        }

        // The reason is part of the return audit record and is mandatory: a
        // restock may only happen against a documented, verified return.
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'ফেরতের কারণ আবশ্যক।',
            ]);
        }

        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => 'ফেরতের কারণ ৫০০ অক্ষরের বেশি হতে পারবে না।',
            ]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'returns' => 'অন্তত একটি পণ্যের ফেরত পরিমাণ দিন।',
            ]);
        }

        $requestedUnits = $this->normalizeReturnLines($lines);

        return DB::transaction(function () use (
            $order,
            $actor,
            $requestedUnits,
            $operationKey,
            $reason,
        ): OrderReturnBatch {
            // Serialise every return for this order, including duplicate
            // operation-key submissions.
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            $existing = OrderReturnBatch::query()
                ->where('order_id', $lockedOrder->id)
                ->where('operation_key', $operationKey)
                ->first();

            if ($existing !== null) {
                return $existing->load('returns');
            }

            if (! $lockedOrder->isDispatched()) {
                throw ValidationException::withMessages([
                    'order' => 'শুধুমাত্র ডিসপ্যাচ করা অর্ডারের যাচাইকৃত ফেরত গ্রহণ করা যাবে।',
                ]);
            }

            $itemIds = array_keys($requestedUnits);
            sort($itemIds);

            // Lock order-item rows in a deterministic order. This is the
            // serialisation point for cumulative return limits.
            $items = $lockedOrder->items()
                ->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($itemIds as $itemId) {
                if (! $items->has($itemId)) {
                    throw ValidationException::withMessages([
                        'returns' => 'ফেরতের জন্য নির্বাচিত আইটেম এই অর্ডারের অংশ নয়।',
                    ]);
                }
            }

            $validatedLines = [];
            $restockByVariant = [];

            foreach ($itemIds as $itemId) {
                /** @var OrderItem $item */
                $item = $items->get($itemId);
                $requested = $requestedUnits[$itemId];
                $maximum = $item->dispatchedWeightUnits();

                // Locking-read the previous return rows avoids relying on a
                // REPEATABLE READ snapshot that could predate a concurrent
                // return committed while this transaction waited on the item
                // row lock.
                $previousReturns = $item->returns()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $item->setRelation('returns', $previousReturns);

                $alreadyReturned = $item->returnedWeightUnits();
                $remaining = $maximum - $alreadyReturned;

                if ($requested <= 0) {
                    throw ValidationException::withMessages([
                        'returns' => 'ফেরতের পরিমাণ শূন্যের বেশি হতে হবে।',
                    ]);
                }

                if ($requested > $remaining) {
                    throw ValidationException::withMessages([
                        'returns' => sprintf(
                            '%s — এই আইটেমের সর্বোচ্চ %s কেজি ফেরতযোগ্য; ইতিমধ্যে %s কেজি ফেরত হয়েছে, বাকি %s কেজি।',
                            $item->product_title.' '.$item->variant_label,
                            Kilograms::format($maximum),
                            Kilograms::format($alreadyReturned),
                            Kilograms::format(max(0, $remaining)),
                        ),
                    ]);
                }

                $variantId = (int) $item->product_variant_id;

                if ($variantId <= 0) {
                    throw ValidationException::withMessages([
                        'returns' => 'এই আইটেমের ভ্যারিয়েন্ট আর স্টকে নেই, তাই ফেরত যোগ করা যাবে না।',
                    ]);
                }

                $validatedLines[] = [
                    'order_item_id' => (int) $item->id,
                    'product_variant_id' => $variantId,
                    'units' => $requested,
                ];

                $restockByVariant[$variantId] = ($restockByVariant[$variantId] ?? 0) + $requested;
            }

            $batch = OrderReturnBatch::create([
                'reference' => $this->generateReturnReference(),
                'order_id' => $lockedOrder->id,
                'operation_key' => $operationKey,
                'actor_user_id' => $actor->id,
                'reason' => $reason,
                'received_at' => now(),
                'approved_at' => now(),
            ]);

            foreach ($validatedLines as $line) {
                $batch->returns()->create([
                    'order_id' => $lockedOrder->id,
                    'order_item_id' => $line['order_item_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'quantity_kg' => Kilograms::fromUnits($line['units']),
                ]);
            }

            // Lock stock rows in ascending variant order and apply the
            // physical increase once per variant, even if two order lines
            // happen to share the same variant.
            ksort($restockByVariant);

            foreach ($restockByVariant as $variantId => $units) {
                $level = $this->lockStockLevel($variantId);

                $level->on_hand_kg = Kilograms::fromUnits($level->onHandUnits() + $units);
                $level->syncAvailable();
                $level->save();
            }

            foreach ($validatedLines as $line) {
                $this->recordMovement(
                    variantId: $line['product_variant_id'],
                    type: StockMovement::TYPE_RETURN,
                    bucket: StockMovement::BUCKET_PHYSICAL,
                    direction: StockMovement::DIRECTION_IN,
                    kilograms: Kilograms::fromUnits($line['units']),
                    operationKey: 'return:batch:'.$batch->id.':item:'.$line['order_item_id'],
                    order: $lockedOrder,
                    actor: $actor,
                    note: 'যাচাইকৃত ও অনুমোদিত ফেরত পণ্য পুনরায় স্টকে যোগ হয়েছে।',
                );
            }

            return $batch->load('returns');
        }, 3);
    }

    /**
     * Release every reservation whose order is still pending past expires_at.
     *
     * @return array{candidates:int, expired:int, skipped:int}
     */
    public function expirePendingReservations(?int $limit = null, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $limit = max(1, $limit ?? (int) config('orders.expiry_batch_size', 100));

        $orderIds = StockReservation::query()
            ->where('status', StockReservation::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->whereHas('order', fn ($query) => $query->where('status', Order::STATUS_PENDING))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('order_id')
            ->unique()
            ->values();

        $expired = 0;
        $skipped = 0;

        foreach ($orderIds as $orderId) {
            if ($this->expireOrderReservations((int) $orderId, $now)) {
                $expired++;
            } else {
                $skipped++;
            }
        }

        return [
            'candidates' => $orderIds->count(),
            'expired' => $expired,
            'skipped' => $skipped,
        ];
    }

    /**
     * Expire one order's pending reservation(s) if it is still eligible.
     *
     * Returns true only when this call performed the pending → cancelled
     * transition. Concurrent acceptance/cancellation makes it return false,
     * so stock is released at most once.
     */
    public function expireOrderReservations(int $orderId, ?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return DB::transaction(function () use ($orderId, $now): bool {
            // The order row is the lifecycle mutex shared with
            // accept/cancel/return.
            $lockedOrder = Order::query()->lockForUpdate()->find($orderId);

            if ($lockedOrder === null || $lockedOrder->status !== Order::STATUS_PENDING) {
                return false;
            }

            $reservations = StockReservation::query()
                ->where('order_id', $lockedOrder->id)
                ->where('status', StockReservation::STATUS_PENDING)
                ->orderBy('product_variant_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                return false;
            }

            $hasDueReservation = $reservations->contains(
                static fn (StockReservation $reservation): bool => $reservation->expires_at !== null
                    && $reservation->expires_at->lessThanOrEqualTo($now),
            );

            if (! $hasDueReservation) {
                return false;
            }

            // If one line is due, the whole pending order is expired. Release
            // every still-pending reservation for that order exactly once.
            foreach ($reservations as $reservation) {
                $this->releaseReservation(
                    $reservation,
                    null,
                    'নির্ধারিত সময়ের মধ্যে অর্ডার গ্রহণ না করায় রিজার্ভেশন স্বয়ংক্রিয়ভাবে ছেড়ে দেওয়া হয়েছে।',
                    StockReservation::RELEASE_EXPIRED,
                );
            }

            $lockedOrder->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => 'expiry',
            ]);

            $this->appendStatusEvent(
                $lockedOrder,
                Order::STATUS_PENDING,
                Order::STATUS_CANCELLED,
                null,
                'নির্ধারিত সময়ের মধ্যে অর্ডার গ্রহণ না করায় রিজার্ভেশন স্বয়ংক্রিয়ভাবে ছাড়া হয়েছে।',
                'system',
            );

            return true;
        }, 3);
    }

    /**
     * @param  array<int, array{order_item_id:int|string, quantity_kg:int|float|string}>  $lines
     * @return array<int, int> order item id => requested units
     */
    private function normalizeReturnLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw ValidationException::withMessages([
                    'returns' => 'ফেরতের প্রতিটি লাইনে আইটেম ও পরিমাণ দিতে হবে।',
                ]);
            }

            $itemId = (int) ($line['order_item_id'] ?? 0);

            if ($itemId <= 0) {
                throw ValidationException::withMessages([
                    'returns' => 'ফেরতের জন্য সঠিক অর্ডার আইটেম নির্বাচন করুন।',
                ]);
            }

            try {
                $units = Kilograms::toUnits($line['quantity_kg'] ?? null);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    'returns' => 'ফেরতের পরিমাণ সঠিক কেজি মান হতে হবে (সর্বোচ্চ ৪ দশমিক স্থান)।',
                ]);
            }

            if ($units <= 0) {
                throw ValidationException::withMessages([
                    'returns' => 'ফেরতের পরিমাণ শূন্যের বেশি হতে হবে।',
                ]);
            }

            // Duplicate item lines in one submission are merged before the
            // cumulative check, so they cannot bypass the limit.
            $normalized[$itemId] = ($normalized[$itemId] ?? 0) + $units;
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{variant_id:int, requested_kg:float}>  $lines
     * @param  array<int, StockLevel>  $levels
     */
    private function assertStockAvailable(array $lines, array $levels): void
    {
        foreach ($lines as $line) {
            $level = $levels[$line['variant_id']] ?? null;
            $requested = Kilograms::toUnits($line['requested_kg'] ?? 0);

            if ($level === null || $level->availableUnits() < $requested) {
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
        $requested = Kilograms::toUnits($kilograms);
        $level = $this->lockStockLevel($variantId);

        if ($level->availableUnits() < $requested) {
            throw ValidationException::withMessages([
                'items' => 'স্টক রিজার্ভ করা যায়নি — অন্য একটি চেকআউট একই স্টক নিয়ে নিয়েছে।',
            ]);
        }

        $level->reserved_kg = Kilograms::fromUnits($level->reservedUnits() + $requested);
        $level->syncAvailable();
        $level->save();

        $ttlHours = (int) config('orders.reservation_ttl_hours', 24);

        StockReservation::create([
            'product_variant_id' => $variantId,
            'order_id' => $order->id,
            'reserved_kg' => Kilograms::fromUnits($requested),
            'reference_type' => 'order',
            'reference_id' => (string) $order->id,
            'status' => StockReservation::STATUS_PENDING,
            'expires_at' => $ttlHours > 0 ? now()->addHours($ttlHours) : null,
            'actor_user_id' => $actor->id,
        ]);

        $this->recordMovement(
            variantId: $variantId,
            type: StockMovement::TYPE_RESERVE,
            bucket: StockMovement::BUCKET_RESERVED,
            direction: StockMovement::DIRECTION_IN,
            kilograms: Kilograms::fromUnits($requested),
            operationKey: 'reserve:order:'.$order->id.':variant:'.$variantId,
            order: $order,
            actor: $actor,
            note: 'অর্ডারের জন্য স্টক রিজার্ভ।',
        );
    }

    private function clearReservationExpiry(Order $order): void
    {
        StockReservation::query()
            ->where('order_id', $order->id)
            ->where('status', StockReservation::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->update(['expires_at' => null]);
    }

    private function releaseReservations(
        Order $order,
        ?User $actor,
        ?string $note,
        string $reason,
    ): void {
        $reservations = StockReservation::query()
            ->where('order_id', $order->id)
            ->where('status', StockReservation::STATUS_PENDING)
            ->orderBy('product_variant_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $this->releaseReservation($reservation, $actor, $note, $reason);
        }
    }

    private function releaseReservation(
        StockReservation $reservation,
        ?User $actor,
        ?string $note,
        string $reason,
    ): void {
        if (! $reservation->isPending()) {
            return;
        }

        $variantId = (int) $reservation->product_variant_id;
        $units = Kilograms::toUnits($reservation->reserved_kg);
        $level = $this->lockStockLevel($variantId);

        if ($level->reservedUnits() < $units) {
            throw new DomainException(
                "Reserved stock for variant {$variantId} is below the reservation being released.",
            );
        }

        $level->reserved_kg = Kilograms::fromUnits($level->reservedUnits() - $units);
        $level->syncAvailable();
        $level->save();

        $reservation->status = StockReservation::STATUS_RELEASED;
        $reservation->released_at = now();
        $reservation->release_reason = $reason;
        $reservation->save();

        $operationKey = 'release:order:'.$reservation->order_id.':variant:'.$variantId;

        if (StockMovement::query()->where('operation_key', $operationKey)->exists()) {
            throw new DomainException(
                "Release movement {$operationKey} already exists while the reservation is still pending.",
            );
        }

        $this->recordMovement(
            variantId: $variantId,
            type: StockMovement::TYPE_RELEASE,
            bucket: StockMovement::BUCKET_RESERVED,
            direction: StockMovement::DIRECTION_OUT,
            kilograms: Kilograms::fromUnits($units),
            operationKey: $operationKey,
            order: $reservation->order,
            actor: $actor,
            note: $note,
        );
    }

    private function dispatchReservations(Order $order, User $actor): void
    {
        $reservations = StockReservation::query()
            ->where('order_id', $order->id)
            ->where('status', StockReservation::STATUS_PENDING)
            ->orderBy('product_variant_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            $variantId = (int) $reservation->product_variant_id;
            $units = Kilograms::toUnits($reservation->reserved_kg);
            $level = $this->lockStockLevel($variantId);

            if ($level->onHandUnits() < $units || $level->reservedUnits() < $units) {
                throw new DomainException(
                    "Cannot dispatch variant {$variantId}: on-hand or reserved stock is below the reservation.",
                );
            }

            $level->on_hand_kg = Kilograms::fromUnits($level->onHandUnits() - $units);
            $level->reserved_kg = Kilograms::fromUnits($level->reservedUnits() - $units);
            $level->syncAvailable();
            $level->save();

            $reservation->status = StockReservation::STATUS_FULFILLED;
            $reservation->fulfilled_at = now();
            $reservation->expires_at = null;
            $reservation->save();

            $this->recordMovement(
                variantId: $variantId,
                type: StockMovement::TYPE_DISPATCH,
                bucket: StockMovement::BUCKET_PHYSICAL,
                direction: StockMovement::DIRECTION_OUT,
                kilograms: Kilograms::fromUnits($units),
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
                kilograms: Kilograms::fromUnits($units),
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
        ?string $actorRole = null,
    ): void {
        OrderStatusEvent::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actorRole ?? $actor?->role,
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
        int|float|string $kilograms,
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

    private function generateReturnReference(): string
    {
        do {
            $reference = 'RET-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (OrderReturnBatch::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
