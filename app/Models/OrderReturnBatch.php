<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturnBatch extends Model
{
    protected $fillable = [
        'reference',
        'order_id',
        'operation_key',
        'actor_user_id',
        'reason',
        'received_at',
        'approved_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }
}
