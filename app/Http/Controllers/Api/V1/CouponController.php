<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends ApiController
{
    /**
     * Validate a coupon code for the authenticated user and cart subtotal.
     */
    public function validateCoupon(Request $request, CouponService $couponService): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $code = trim($validated['code']);
        $subtotal = (float) $validated['subtotal'];

        $result = $couponService->validate($code, $user, $subtotal);

        if (! $result['valid']) {
            return $this->errorResponse($result['message'], 422, [
                'code' => [$result['message']],
            ]);
        }

        return $this->successResponse([
            'valid' => true,
            'message' => $result['message'],
            'discount' => $result['discount'],
            'coupon' => [
                'id' => $result['coupon']->id,
                'code' => $result['coupon']->code,
                'type' => $result['coupon']->type,
                'value' => (float) $result['coupon']->value,
            ],
        ], 'Coupon is valid');
    }
}
