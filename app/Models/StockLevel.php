<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLevel extends Model
{
    use HasFactory, SoftDeletes;

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

    public function getAvailableAttribute(): float
    {
        return max(0, (float) $this->on_hand_kg - (float) $this->reserved_kg);
    }
}
