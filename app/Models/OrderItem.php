<?php

namespace App\Models;

use App\Support\Kilograms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_title',
        'variant_label',
        'unit',
        'weight_kg',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function weightGrams(): int
    {
        return (int) round(((float) $this->weight_kg) * 1000);
    }

    /**
     * Historical dispatched weight for this line, in 0.0001 kg units.
     *
     * Uses the weight snapshot stored on the order item, never the current
     * catalogue weight, so later product edits cannot change return limits.
     */
    public function dispatchedWeightUnits(): int
    {
        return Kilograms::toUnits($this->weight_kg) * max(0, (int) $this->quantity);
    }

    public function returnedWeightUnits(): int
    {
        $returns = $this->relationLoaded('returns')
            ? $this->returns
            : $this->returns()->get();

        return $returns->reduce(
            static fn (int $carry, OrderReturn $return): int => $carry
                + Kilograms::toUnits($return->quantity_kg),
            0,
        );
    }

    public function remainingReturnableUnits(): int
    {
        return max(0, $this->dispatchedWeightUnits() - $this->returnedWeightUnits());
    }

    public function dispatchedWeightLabel(): string
    {
        return Kilograms::formatKg($this->dispatchedWeightUnits());
    }

    public function returnedWeightLabel(): string
    {
        return Kilograms::formatKg($this->returnedWeightUnits());
    }

    public function remainingWeightLabel(): string
    {
        return Kilograms::formatKg($this->remainingReturnableUnits());
    }
}
