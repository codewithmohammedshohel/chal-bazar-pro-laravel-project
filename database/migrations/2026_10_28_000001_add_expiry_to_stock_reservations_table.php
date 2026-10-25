<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pending reservations get an explicit, configurable expiry. The reason
     * column records why a reservation was released (expiry vs. manual
     * cancellation) without rewriting history.
     */
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('released_at');
            $table->string('release_reason', 32)->nullable()->after('expires_at');

            $table->index(['status', 'expires_at'], 'stock_reservations_status_expires_index');
        });

        // Backfill rows created before this feature so legacy pending orders
        // become eligible for the same 24-hour policy.
        DB::table('stock_reservations')
            ->where('status', 'pending')
            ->whereNull('expires_at')
            ->chunkById(500, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    DB::table('stock_reservations')
                        ->where('id', $reservation->id)
                        ->update([
                            'expires_at' => \Illuminate\Support\Carbon::parse($reservation->created_at)
                                ->addHours(24),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropIndex('stock_reservations_status_expires_index');
            $table->dropColumn(['expires_at', 'release_reason']);
        });
    }
};
