<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'method',
        'amount',
        'status',
        'gateway_transaction_id',
        'gateway_response',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Total amount refunded across all completed/processing refunds.
     */
    public function getRefundedAmountAttribute(): float
    {
        return (float) $this->refunds()
            ->whereIn('status', ['completed', 'processing'])
            ->sum('amount');
    }

    /**
     * Remaining refundable balance for this payment.
     */
    public function getRemainingRefundableAmountAttribute(): float
    {
        $refunded = $this->refunded_amount;
        return (float) max(0, round((float) $this->amount - $refunded, 2));
    }

    /**
     * Mark an offline or pending payment as paid.
     */
    public function markAsPaid(?string $notes = null): void
    {
        $this->update([
            'status' => 'success',
            'paid_at' => now(),
            'notes' => $notes ?: $this->notes,
        ]);

        if ($this->order) {
            $orderUpdates = [
                'payment_status' => 'paid',
                'paid_at' => now(),
            ];

            // If the order was awaiting online payment or auto-cancelled on timeout, advance status to pending and decrement deferred inventory
            if (in_array($this->order->status, ['pending_payment', 'cancelled'])) {
                $orderUpdates['status'] = 'pending';

                $this->order->loadMissing('items');
                foreach ($this->order->items as $item) {
                    if ($item->product_variant_id) {
                        ProductVariant::where('id', $item->product_variant_id)->decrement('stock', $item->quantity);
                        Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                    } else {
                        Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                    }
                }
            }

            $this->order->update($orderUpdates);

            // If order has farm, ensure expense is recorded in ledger
            if ($this->order->farm_id) {
                FarmLedgerEntry::recordOrderExpense($this->order);
            }
        }
    }

    /**
     * Filter failed or abandoned payments.
     */
    public function scopeFailedOrAbandoned(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status', 'failed')
                ->orWhere(function ($sq) {
                    $sq->where('status', 'pending')
                        ->whereHas('order', function ($oq) {
                            $oq->where('status', 'pending_payment')
                                ->where('created_at', '<=', now()->subMinutes(30));
                        });
                });
        });
    }

    /**
     * Normalized sub-method (e.g. bKash, Nagad, Rocket, Visa, Mastercard).
     */
    public function getSubMethodAttribute(): ?string
    {
        if ($this->method !== 'sslcommerz') {
            return null;
        }

        $res = $this->gateway_response ?? [];
        $rawBrand = strtoupper($res['card_brand'] ?? $res['card_type'] ?? '');
        $rawIssuer = strtoupper($res['card_issuer'] ?? '');
        $combined = $rawBrand . ' ' . $rawIssuer;

        if (str_contains($combined, 'BKASH')) {
            return 'bKash';
        }
        if (str_contains($combined, 'NAGAD')) {
            return 'Nagad';
        }
        if (str_contains($combined, 'ROCKET') || str_contains($combined, 'DBBL MOBILE')) {
            return 'Rocket';
        }
        if (str_contains($combined, 'UPAY')) {
            return 'Upay';
        }
        if (str_contains($combined, 'VISA')) {
            return 'Visa';
        }
        if (str_contains($combined, 'MASTER')) {
            return 'Mastercard';
        }
        if (str_contains($combined, 'AMEX') || str_contains($combined, 'AMERICAN EXPRESS')) {
            return 'American Express';
        }
        if (str_contains($combined, 'NEXUS')) {
            return 'DBBL Nexus';
        }
        if (str_contains($combined, 'CITY TOUCH')) {
            return 'City Touch';
        }
        if (str_contains($combined, 'ISLAMI') || str_contains($combined, 'IBBL')) {
            return 'IBBL Net Banking';
        }

        return ! empty($res['card_brand']) ? ucfirst(strtolower($res['card_brand'])) : (! empty($res['card_type']) ? $res['card_type'] : null);
    }

    public function getCardTypeAttribute(): ?string
    {
        return $this->gateway_response['card_type'] ?? null;
    }

    public function getCardBrandAttribute(): ?string
    {
        return $this->gateway_response['card_brand'] ?? null;
    }

    public function getCardIssuerAttribute(): ?string
    {
        return $this->gateway_response['card_issuer'] ?? null;
    }

    public function getBankTranIdAttribute(): ?string
    {
        return $this->gateway_response['bank_tran_id'] ?? null;
    }

    public function getMaskedAccountAttribute(): ?string
    {
        return $this->gateway_response['card_no'] ?? null;
    }
}
