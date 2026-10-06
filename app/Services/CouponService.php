<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validate a coupon code for a given farmer and order subtotal.
     *
     * @return array{valid: bool, coupon: Coupon|null, discount: float, message: string}
     */
    public function validate(string $code, User $farmer, float $subtotal): array
    {
        $cleanCode = strtoupper(trim($code));
        $coupon = Coupon::where('code', $cleanCode)->first();

        if (! $coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'discount' => 0.0,
                'message' => 'Invalid discount code.',
            ];
        }

        $reason = null;
        if (! $coupon->isValidFor($farmer, $subtotal, $reason)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => 0.0,
                'message' => $reason ?? 'Discount code is not valid for this order.',
            ];
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'message' => sprintf(
                'Discount code %s applied! Saved ৳%s',
                $coupon->code,
                number_format($discount, 2)
            ),
        ];
    }

    /**
     * Atomically redeem a validated coupon for an order.
     */
    public function redeem(Coupon $coupon, Order $order, User $farmer, float $discountAmount): CouponRedemption
    {
        return CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'farmer_id' => $farmer->id,
            'discount_amount' => $discountAmount,
            'redeemed_at' => now(),
        ]);
    }
}
