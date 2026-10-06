<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends ApiController
{
    /**
     * Display a listing of coupons with redemption counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::withCount('redemptions');

        if ($request->filled('status')) {
            if ($request->query('status') === 'active') {
                $query->where('active', true);
            } elseif ($request->query('status') === 'inactive') {
                $query->where('active', false);
            }
        }

        if ($request->filled('search')) {
            $search = strtoupper(trim($request->query('search')));
            $query->where('code', 'like', "%{$search}%");
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $coupons = $query->latest()->paginate($perPage);

        return $this->successResponse($coupons);
    }

    /**
     * Store a newly created coupon.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:coupons,code'],
            'type' => ['required', 'string', 'in:percentage,fixed_amount'],
            'value' => ['required', 'numeric', 'min:0.01', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('type') === 'percentage' && $value > 100) {
                    $fail('Percentage discount cannot exceed 100%.');
                }
            }],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_farmer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $coupon = Coupon::create($validated);

        ActivityLog::log(
            'coupon.created',
            $coupon,
            [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'active' => $coupon->active,
            ],
            $request->user()?->id
        );

        return $this->createdResponse($coupon, 'Coupon created successfully');
    }

    /**
     * Display the specified coupon with recent redemptions.
     */
    public function show(Coupon $coupon): JsonResponse
    {
        $coupon->load(['redemptions.order', 'redemptions.farmer'])->loadCount('redemptions');

        return $this->successResponse($coupon);
    }

    /**
     * Update the specified coupon.
     */
    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'alpha_dash', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'type' => ['sometimes', 'required', 'string', 'in:percentage,fixed_amount'],
            'value' => ['sometimes', 'required', 'numeric', 'min:0.01', function ($attribute, $value, $fail) use ($request, $coupon) {
                $type = $request->input('type', $coupon->type);
                if ($type === 'percentage' && $value > 100) {
                    $fail('Percentage discount cannot exceed 100%.');
                }
            }],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_farmer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'active' => ['boolean'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $old = $coupon->toArray();
        $coupon->update($validated);

        ActivityLog::log(
            'coupon.updated',
            $coupon,
            [
                'old' => $old,
                'new' => $coupon->toArray(),
            ],
            $request->user()?->id
        );

        return $this->successResponse($coupon, 'Coupon updated successfully');
    }

    /**
     * Toggle coupon active state.
     */
    public function toggleActive(Request $request, Coupon $coupon): JsonResponse
    {
        $coupon->active = ! $coupon->active;
        $coupon->save();

        ActivityLog::log(
            'coupon.status_toggled',
            $coupon,
            [
                'code' => $coupon->code,
                'active' => $coupon->active,
            ],
            $request->user()?->id
        );

        return $this->successResponse($coupon, 'Coupon status toggled successfully');
    }
}
