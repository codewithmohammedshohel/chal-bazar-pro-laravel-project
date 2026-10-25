<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'orders:expire-reservations
        {--limit= : Maximum number of due reservations to inspect in this run}';

    protected $description = 'Cancel pending orders whose reservation has expired and release their stock';

    public function handle(OrderService $orders): int
    {
        $limit = $this->option('limit');

        if ($limit !== null && (! is_numeric($limit) || (int) $limit < 1)) {
            $this->error('The --limit option must be a positive integer.');

            return self::FAILURE;
        }

        $result = $orders->expirePendingReservations(
            $limit === null ? null : (int) $limit,
        );

        $this->info(sprintf(
            'Reservation expiry complete: %d due candidate(s), %d order(s) expired, %d skipped.',
            $result['candidates'],
            $result['expired'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
