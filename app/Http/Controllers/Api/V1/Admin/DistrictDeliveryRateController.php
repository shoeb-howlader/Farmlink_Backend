<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\DistrictDeliveryRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistrictDeliveryRateController extends ApiController
{
    /**
     * List all district-specific delivery rates with their district and division.
     */
    public function index(): JsonResponse
    {
        $rates = DistrictDeliveryRate::with(['district.division'])
            ->get()
            ->sortBy(fn ($rate) => $rate->district?->name ?? '')
            ->values();

        return $this->successResponse($rates);
    }

    /**
     * Store a new district-specific delivery rate override.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'district_id' => ['required', 'integer', 'exists:districts,id', 'unique:district_delivery_rates,district_id'],
            'fee' => ['required', 'numeric', 'min:0'],
            'free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],
        ], [
            'district_id.unique' => 'A delivery rate override for this district already exists. Edit the existing rate instead.',
        ]);

        $rate = DistrictDeliveryRate::create([
            'district_id' => $validated['district_id'],
            'fee' => $validated['fee'],
            'free_delivery_threshold' => $validated['free_delivery_threshold'] ?? null,
        ]);

        $rate->load(['district.division']);

        ActivityLog::log(
            'district_delivery_rate.created',
            $rate,
            [
                'district' => $rate->district?->name,
                'fee' => (float) $rate->fee,
                'free_delivery_threshold' => $rate->free_delivery_threshold !== null ? (float) $rate->free_delivery_threshold : null,
            ],
            $request->user()
        );

        return $this->createdResponse($rate, 'District delivery rate override created successfully');
    }

    /**
     * Update an existing district delivery rate override.
     */
    public function update(Request $request, DistrictDeliveryRate $districtDeliveryRate): JsonResponse
    {
        $validated = $request->validate([
            'fee' => ['required', 'numeric', 'min:0'],
            'free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        $oldFee = (float) $districtDeliveryRate->fee;
        $oldThreshold = $districtDeliveryRate->free_delivery_threshold !== null ? (float) $districtDeliveryRate->free_delivery_threshold : null;

        $districtDeliveryRate->update([
            'fee' => $validated['fee'],
            'free_delivery_threshold' => $validated['free_delivery_threshold'] ?? null,
        ]);

        $districtDeliveryRate->load(['district.division']);

        ActivityLog::log(
            'district_delivery_rate.updated',
            $districtDeliveryRate,
            [
                'district' => $districtDeliveryRate->district?->name,
                'old_fee' => $oldFee,
                'new_fee' => (float) $districtDeliveryRate->fee,
                'old_threshold' => $oldThreshold,
                'new_threshold' => $districtDeliveryRate->free_delivery_threshold !== null ? (float) $districtDeliveryRate->free_delivery_threshold : null,
            ],
            $request->user()
        );

        return $this->successResponse($districtDeliveryRate, 'District delivery rate override updated successfully');
    }

    /**
     * Remove a district-specific delivery rate override.
     */
    public function destroy(Request $request, DistrictDeliveryRate $districtDeliveryRate): JsonResponse
    {
        $districtName = $districtDeliveryRate->district?->name;

        ActivityLog::log(
            'district_delivery_rate.deleted',
            null,
            [
                'district' => $districtName,
                'deleted_rate_id' => $districtDeliveryRate->id,
                'fee' => (float) $districtDeliveryRate->fee,
            ],
            $request->user()
        );

        $districtDeliveryRate->delete();

        return $this->successResponse(null, "Delivery rate override for {$districtName} removed successfully");
    }
}
