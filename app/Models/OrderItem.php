<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function weightGrams(): int
    {
        return (int) round(((float) $this->weight_kg) * 1000);
    }
}
