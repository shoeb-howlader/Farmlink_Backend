<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends ApiController
{
    /**
     * Get active payment methods for checkout and POS payment selection.
     */
    public function index(): JsonResponse
    {
        $methods = PaymentMethod::active()->get();

        return $this->successResponse($methods)
            ->header('Cache-Control', 'public, max-age=600, stale-while-revalidate=1800');
    }
}
