<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InvoiceSequence extends Model
{
    protected $fillable = ['period', 'last_number'];

    protected $casts = ['last_number' => 'integer'];

    /**
     * Reserve the next invoice number for the given period (YYYYMM) using a
     * row lock so concurrent checkouts cannot receive the same number.
     */
    public static function nextNumber(?string $period = null): string
    {
        $period ??= now()->format('Ym');

        return DB::transaction(function () use ($period): string {
            DB::table('invoice_sequences')->insertOrIgnore([
                'period' => $period,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = static::query()
                ->where('period', $period)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');
            $sequence->refresh();

            return sprintf('INV-%s-%06d', $period, $sequence->last_number);
        });
    }
}
