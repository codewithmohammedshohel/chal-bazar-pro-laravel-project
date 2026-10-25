<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pending reservation lifetime
    |--------------------------------------------------------------------------
    |
    | Orders that stay pending (not accepted) for this many hours become
    | eligible for automatic cancellation and stock release. Set to 0 to
    | disable automatic expiry (not recommended in production).
    |
    */
    'reservation_ttl_hours' => (int) env('ORDER_RESERVATION_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Expiry batch size
    |--------------------------------------------------------------------------
    |
    | Maximum number of due reservations inspected per scheduler run. Each
    | eligible order is still processed in its own transaction.
    |
    */
    'expiry_batch_size' => (int) env('ORDER_RESERVATION_EXPIRY_BATCH_SIZE', 100),
];
