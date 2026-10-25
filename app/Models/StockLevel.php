<?php

namespace App\Models;

use App\Support\Kilograms;
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

    public function onHandUnits(): int
    {
        return Kilograms::toUnits($this->on_hand_kg);
    }

    public function reservedUnits(): int
    {
        return Kilograms::toUnits($this->reserved_kg);
    }

    public function availableUnits(): int
    {
        return $this->onHandUnits() - $this->reservedUnits();
    }

    /**
     * Available stock is always derived from the two authoritative buckets.
     */
    public function available(): float
    {
        return Kilograms::toFloat($this->availableUnits());
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
        $available = $this->availableUnits();

        if ($available < 0) {
            throw new \DomainException(
                "Stock level {$this->id} would have negative available stock ({$available} units).",
            );
        }

        $this->available_kg = Kilograms::fromUnits($available);
    }
}
