<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    /** Baseline forward lifecycle. */
    public const FLOW = [
        self::STATUS_PENDING => [self::STATUS_ACCEPTED, self::STATUS_CANCELLED],
        self::STATUS_ACCEPTED => [self::STATUS_IN_TRANSIT, self::STATUS_CANCELLED],
        self::STATUS_IN_TRANSIT => [self::STATUS_DELIVERED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
    ];

    public const PAYMENT_COD = 'COD';

    protected $fillable = [
        'code',
        'user_id',
        'status',
        'payment_mode',
        'subtotal',
        'delivery_fee',
        'total',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'distance_km',
        'customer_note',
        'idempotency_key',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'delivery_lat' => 'decimal:7',
        'delivery_lng' => 'decimal:7',
        'distance_km' => 'decimal:3',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::FLOW[$this->status] ?? [], true);
    }

    public function isDispatched(): bool
    {
        return $this->dispatched_at !== null
            || in_array($this->status, [self::STATUS_IN_TRANSIT, self::STATUS_DELIVERED], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
