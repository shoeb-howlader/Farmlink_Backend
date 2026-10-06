<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'minimum_order_amount',
        'usage_limit_total',
        'usage_limit_per_farmer',
        'starts_at',
        'expires_at',
        'is_active',
        'active',
    ];

    protected $appends = [
        'active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function setActiveAttribute($value): void
    {
        $this->attributes['is_active'] = (bool) $value;
    }

    public function getActiveAttribute(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? true);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Check if this coupon is valid for a given farmer and subtotal.
     */
    public function isValidFor(User $farmer, float $subtotal, ?string &$failureReason = null): bool
    {
        if (! $this->is_active) {
            $failureReason = 'This discount code is inactive.';
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            $failureReason = 'This discount code is not yet active.';
            return false;
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            $failureReason = 'This discount code has expired.';
            return false;
        }

        if ($this->minimum_order_amount !== null && $subtotal < (float) $this->minimum_order_amount) {
            $failureReason = sprintf('A minimum order subtotal of ৳%s is required to apply this code.', number_format((float) $this->minimum_order_amount, 2));
            return false;
        }

        if ($this->usage_limit_total !== null) {
            $totalUses = $this->redemptions()->count();
            if ($totalUses >= $this->usage_limit_total) {
                $failureReason = 'This discount code has reached its total usage limit.';
                return false;
            }
        }

        if ($this->usage_limit_per_farmer !== null) {
            $farmerUses = $this->redemptions()->where('farmer_id', $farmer->id)->count();
            if ($farmerUses >= $this->usage_limit_per_farmer) {
                $failureReason = 'You have already reached the maximum usage limit for this discount code.';
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate discount amount given an order subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === 'percentage') {
            $discount = round($subtotal * ((float) $this->value / 100), 2);
            return min($discount, $subtotal);
        }

        // Fixed amount discount
        return min((float) $this->value, $subtotal);
    }
}
