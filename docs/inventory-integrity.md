# Inventory integrity and reservation expiry

This document describes the Milestone 5B.1 hardening rules and the manual
verification steps. It does not replace the automated tests.

## Stock buckets

`stock_levels` keeps two authoritative decimal(12,4) buckets:

- `on_hand_kg` — physical stock currently in the shop.
- `reserved_kg` — stock promised to pending/accepted orders but not dispatched.

`available_kg` is a cached derivation of `on_hand_kg - reserved_kg`. All
arithmetic goes through `App\Support\Kilograms`, which converts kilograms to
integer 1/10,000 kg units before comparing or adding them. No stock limit is
decided with floating-point comparisons.

| Operation | on_hand | reserved | Ledger |
| --- | --- | --- | --- |
| Place order | unchanged | `+kg` | `reserve` (reserved/in) |
| Accept order | unchanged | unchanged | status event only; expiry cleared |
| Dispatch | `-kg` | `-kg` | `dispatch` (physical/out and reserved/out) |
| Deliver | unchanged | unchanged | status event only |
| Cancel before dispatch | unchanged | `-kg` | `release` (reserved/out) |
| Return approved | `+kg` | unchanged | `return` (physical/in) |

Every mutation carries an `operation_key` unique in `stock_movements`. A
repeated request cannot create a second movement for the same operation.

## Returns

### Data model

- `order_return_batches` — one row per admin submission. Holds the server
  reference (`RET-YYYYMMDD-XXXXXX`), order, unique `(order_id, operation_key)`,
  actor, reason, `received_at` and `approved_at`.
- `order_returns` — one row per returned order line. Holds the order item,
  product variant snapshot, and exact `quantity_kg`.

The cumulative returned quantity is derived from `order_returns`, not from a
single stock-movement key. This is what closes the "different operation keys"
gap.

### Limit calculation

For each order item:

```text
maximum_dispatched_kg = order_items.weight_kg × order_items.quantity
previously_returned_kg = SUM(order_returns.quantity_kg for that order item)
requested_kg           = the new line quantity
```

The request is accepted only when:

```text
previously_returned_kg + requested_kg <= maximum_dispatched_kg
```

The historical `weight_kg` snapshot on `order_items` is used, so editing the
catalogue weight later cannot increase the returnable quantity.

### Eligibility and atomicity

A return is accepted only when:

- `dispatched_at` is set, including an order the shop cancelled after
  dispatch (the goods still physically left the shop);
- the order is **not** a cancelled order that was never dispatched;
- the order item belongs to that order;
- the line has a positive quantity with at most four decimal places;
- a non-blank reason of at most 500 characters is supplied and stored on the
  return batch.

The service locks the order row, the affected order-item rows (ascending id),
the previous return rows, and stock rows (ascending variant id). All inserts
and stock updates happen in one transaction. If any line fails, every line is
rolled back and no stock movement is written.

Recording a return with the `inventory` permission is the verification and
approval action. `approved_at` is written only after every check has passed,
in the same transaction that increases physical stock.

### Admin flow

`/admin/orders/{order}` shows, per item, dispatched / already returned /
remaining kilograms. Submit one or more item quantities with a mandatory
reason and an operation key. Reusing the same operation key returns the
original batch and does not restock again.

## Reservation expiry

### Policy

- New pending reservations get `expires_at = now + ORDER_RESERVATION_TTL_HOURS`
  (default `24`). Set the variable to `0` to disable automatic expiry.
- Accepting an order clears `expires_at` on its pending reservations.
- Expiry only considers orders that are still `pending`. Accepted, dispatched,
  delivered and cancelled orders are never released by the command.
- When at least one reservation for a pending order is due, the whole pending
  order is cancelled with `cancelled_by = 'expiry'`, all its pending
  reservations are released with `release_reason = 'expired'`, and a system
  status event is appended. No history is deleted.

### Command

```bash
php artisan orders:expire-reservations
php artisan orders:expire-reservations --limit=50
```

Output example:

```text
Reservation expiry complete: 2 due candidate(s), 2 order(s) expired, 0 skipped.
```

The command is safe to run repeatedly. A second run finds no pending
reservations and writes no new movements. It is also safe to overlap with an
admin accepting or cancelling the same order because both paths lock the order
row and re-check its status inside the transaction.

### Scheduler

`routes/console.php` schedules `orders:expire-reservations` every ten minutes
with `withoutOverlapping()`. The application scheduler must be running, for
example during local verification:

```bash
php artisan schedule:list
php artisan schedule:work
```

cPanel cron wiring is a later deployment task and is not assumed to be active.

### Manual expiry test

1. Place an order and leave it `pending`.
2. Confirm `stock_reservations.expires_at` is approximately 24 hours ahead and
   `reserved_kg` increased.
3. Set the reservation's `expires_at` into the past in a disposable database.
4. Run `php artisan orders:expire-reservations`.
5. Confirm the order is `cancelled` with `cancelled_by = expiry`, reserved stock
   is back to its previous value, physical stock did not change, one `release`
   movement exists, and one system status event was appended.
6. Run the command again and confirm no additional movement or status event is
   created.
7. Repeat with an accepted order and confirm it is untouched.

## MySQL concurrency verification

The tests in `tests/Feature/Orders/ReturnConcurrencyTest.php` start separate PHP
processes, each with its own PDO connection. Workers write a readiness file and
wait for a shared barrier before executing, so transactions genuinely overlap.

They verify final database balances and ledger rows, not just HTTP responses:

- two checkouts competing for the same limited stock;
- two returns competing for the remaining returnable quantity;
- concurrent duplicate operation keys;
- expiry racing with acceptance;
- expiry racing with customer cancellation.

Run them only against a disposable MySQL test database:

```bash
php artisan migrate --force
RUN_MYSQL_CONCURRENCY=1 php artisan test tests/Feature/Orders/ReturnConcurrencyTest.php
```

The class skips itself on SQLite and refuses any database whose name does not
contain `test`. It uses committed fixtures and removes only the rows it created;
it does not truncate or migrate:fresh the database.

## Running the normal suites

```bash
php artisan test
```

For MySQL, run the additive migrations first, then override the connection:

```bash
php artisan migrate --force
DB_CONNECTION=mysql DB_DATABASE=your_test_db \
  DB_USERNAME=... DB_PASSWORD=... php artisan test
```

The test base class uses additive `migrate` plus a transaction on non-memory
databases, so it never calls `migrate:fresh`.
