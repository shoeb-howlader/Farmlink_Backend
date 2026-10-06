<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'order_id',
        'amount',
        'reason',
        'status',
        'restock_items',
        'gateway_refund_ref',
        'gateway_response',
        'initiated_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'restock_items' => 'boolean',
            'gateway_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
