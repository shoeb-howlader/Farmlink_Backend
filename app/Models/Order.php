<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'farm_id',
        'recipient_name',
        'recipient_phone',
        'delivery_address',
        'division_id',
        'district_id',
        'upazila_id',
        'union_id',
        'pourashava_id',
        'district',
        'upazila',
        'union',
        'invoice_number',
        'status',
        'subtotal',
        'delivery_fee',
        'discount_amount',
        'coupon_id',
        'total',
        'channel',
        'payment_mode',
        'payment_status',
        'gateway_transaction_id',
        'gateway_payment_details',
        'paid_at',
        'refund_status',
        'refund_reference',
        'refund_amount',
        'refunded_at',
        'refund_reason',
        'notes',
        'ip_address',
        'user_agent',
        'ip_country',
        'ip_city',
        'ip_region',
        'ip_isp',
        'advance_delivery_fee',
        'advance_paid_at',
        'risk_score',
        'risk_level',
        'is_flagged',
        'flag_reasons',
        'verification_status',
        'verified_by',
        'verified_at',
        'created_at',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->invoice_number)) {
                $order->invoice_number = static::generateNextInvoiceNumber();
            }
        });
    }

    /**
     * Generate the next sequential invoice number for the specified or current year.
     * Format: INV-YYYY-XXXXX (e.g. INV-2026-00001)
     */
    public static function generateNextInvoiceNumber(?int $year = null): string
    {
        $year = $year ?? (int) date('Y');
        $prefix = "INV-{$year}-";

        $lastOrder = static::where('invoice_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        $nextSeq = 1;
        if ($lastOrder && preg_match('/' . preg_quote($prefix, '/') . '(\d+)/', $lastOrder->invoice_number, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%05d', $prefix, $nextSeq);
    }

    /**
     * Get the farm associated with this order.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get the division associated with the delivery address.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the district associated with the delivery address.
     */
    public function districtData(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    /**
     * Get the upazila associated with the delivery address.
     */
    public function upazilaData(): BelongsTo
    {
        return $this->belongsTo(Upazila::class, 'upazila_id');
    }

    /**
     * Get the union associated with the delivery address.
     */
    public function unionData(): BelongsTo
    {
        return $this->belongsTo(Union::class, 'union_id');
    }

    /**
     * Get the pourashava associated with the delivery address.
     */
    public function pourashavaData(): BelongsTo
    {
        return $this->belongsTo(Pourashava::class, 'pourashava_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'advance_delivery_fee' => 'decimal:2',
            'advance_paid_at' => 'datetime',
            'gateway_payment_details' => 'array',
            'risk_score' => 'integer',
            'is_flagged' => 'boolean',
            'flag_reasons' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the staff user who verified this order.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the coupon applied to this order if any.
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get the coupon redemption record for this order.
     */
    public function couponRedemption(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    /**
     * Get the user who placed the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items in this order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all payment transactions for this order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the latest payment transaction for this order.
     */
    public function payment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * Get all refunds for this order.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Normalized sub-method (e.g. bKash, Nagad, Rocket, Visa, Mastercard)
     */
    public function getSubMethodAttribute(): ?string
    {
        if ($this->payment_mode !== 'sslcommerz') {
            return null;
        }

        $res = $this->gateway_payment_details ?? $this->payment?->gateway_response ?? [];
        $rawBrand = strtoupper($res['card_brand'] ?? $res['card_type'] ?? '');
        $rawIssuer = strtoupper($res['card_issuer'] ?? '');
        $combined = $rawBrand . ' ' . $rawIssuer;

        if (str_contains($combined, 'BKASH')) return 'bKash';
        if (str_contains($combined, 'NAGAD')) return 'Nagad';
        if (str_contains($combined, 'ROCKET') || str_contains($combined, 'DBBL MOBILE')) return 'Rocket';
        if (str_contains($combined, 'UPAY')) return 'Upay';
        if (str_contains($combined, 'VISA')) return 'Visa';
        if (str_contains($combined, 'MASTER')) return 'Mastercard';
        if (str_contains($combined, 'AMEX') || str_contains($combined, 'AMERICAN EXPRESS')) return 'American Express';
        if (str_contains($combined, 'NEXUS')) return 'DBBL Nexus';
        if (str_contains($combined, 'CITY TOUCH')) return 'City Touch';
        if (str_contains($combined, 'ISLAMI') || str_contains($combined, 'IBBL')) return 'IBBL Net Banking';

        return ! empty($res['card_brand']) ? ucfirst(strtolower($res['card_brand'])) : (! empty($res['card_type']) ? $res['card_type'] : null);
    }

    public function getBankTranIdAttribute(): ?string
    {
        return $this->gateway_payment_details['bank_tran_id'] ?? $this->payment?->bank_tran_id ?? null;
    }
}

