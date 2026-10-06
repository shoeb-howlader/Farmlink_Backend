<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentMethodController extends ApiController
{
    /**
     * Display a listing of all payment methods.
     */
    public function index(): JsonResponse
    {
        $methods = PaymentMethod::orderBy('sort_order')->orderBy('name')->get();

        return $this->successResponse($methods);
    }

    /**
     * Store a newly created payment method.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:payment_methods,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = strtolower(trim($validated['code']));
        $validated['sort_order'] = $validated['sort_order'] ?? (PaymentMethod::max('sort_order') + 1);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $method = PaymentMethod::create($validated);

        ActivityLog::log(
            'payment_method.created',
            $method,
            [
                'code' => $method->code,
                'name' => $method->name,
                'is_active' => $method->is_active,
            ],
            $request->user()?->id
        );

        return $this->createdResponse($method, 'Payment method created successfully');
    }

    /**
     * Update an existing payment method.
     */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $old = $paymentMethod->toArray();
        $paymentMethod->update($validated);

        ActivityLog::log(
            'payment_method.updated',
            $paymentMethod,
            [
                'old' => $old,
                'new' => $paymentMethod->toArray(),
            ],
            $request->user()?->id
        );

        return $this->successResponse($paymentMethod, 'Payment method updated successfully');
    }

    /**
     * Toggle active state for a payment method.
     */
    public function toggleActive(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->is_active = ! $paymentMethod->is_active;
        $paymentMethod->save();

        ActivityLog::log(
            'payment_method.status_toggled',
            $paymentMethod,
            [
                'code' => $paymentMethod->code,
                'is_active' => $paymentMethod->is_active,
            ],
            $request->user()?->id
        );

        return $this->successResponse($paymentMethod, 'Payment method status toggled successfully');
    }
}
