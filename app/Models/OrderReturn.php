<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturn extends Model
{
    protected $fillable = [
        'order_return_batch_id',
        'order_id',
        'order_item_id',
        'product_variant_id',
        'quantity_kg',
    ];

    protected $casts = [
        'quantity_kg' => 'decimal:4',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OrderReturnBatch::class, 'order_return_batch_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
