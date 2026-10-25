<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'on_hand_kg',
        'reserved_kg',
        'available_kg',
    ];

    protected $casts = [
        'on_hand_kg' => 'decimal:4',
        'reserved_kg' => 'decimal:4',
        'available_kg' => 'decimal:4',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Available stock is always derived, never trusted from the column alone.
     */
    public function available(): float
    {
        return round((float) $this->on_hand_kg - (float) $this->reserved_kg, 4);
    }

    public function getAvailableAttribute(): float
    {
        return $this->available();
    }

    /**
     * Keep the cached available_kg column in sync after either bucket changes.
     */
    public function syncAvailable(): void
    {
        $this->available_kg = $this->available();
    }
}
