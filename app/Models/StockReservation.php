<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_RELEASED = 'released';

    public const RELEASE_CUSTOMER = 'customer_cancelled';
    public const RELEASE_SHOP = 'shop_cancelled';
    public const RELEASE_EXPIRED = 'expired';

    protected $fillable = [
        'product_variant_id',
        'order_id',
        'reserved_kg',
        'reference_type',
        'reference_id',
        'status',
        'fulfilled_at',
        'released_at',
        'expires_at',
        'release_reason',
        'actor_user_id',
    ];

    protected $casts = [
        'reserved_kg' => 'decimal:4',
        'fulfilled_at' => 'datetime',
        'released_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->isPending()
            && $this->expires_at !== null
            && $this->expires_at->lessThanOrEqualTo(now());
    }
}
