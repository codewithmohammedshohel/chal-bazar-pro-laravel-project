<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    /** Which ledger bucket a movement affects. */
    public const BUCKET_PHYSICAL = 'physical';
    public const BUCKET_RESERVED = 'reserved';

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_RESERVE = 'reserve';
    public const TYPE_RELEASE = 'release';
    public const TYPE_DISPATCH = 'dispatch';
    public const TYPE_RETURN = 'return';

    protected $fillable = [
        'product_variant_id',
        'movement_type',
        'stock_bucket',
        'direction',
        'quantity_kg',
        'reference_type',
        'reference_id',
        'operation_key',
        'note',
        'actor_user_id',
    ];

    protected $casts = [
        'quantity_kg' => 'decimal:4',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function isPhysical(): bool
    {
        return $this->stock_bucket === self::BUCKET_PHYSICAL;
    }

    public function isReserved(): bool
    {
        return $this->stock_bucket === self::BUCKET_RESERVED;
    }

    /**
     * Signed kilogram delta, physical or reserved, for reporting.
     */
    public function signedQuantity(): float
    {
        $quantity = (float) $this->quantity_kg;

        return $this->direction === self::DIRECTION_OUT ? -$quantity : $quantity;
    }
}
