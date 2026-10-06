<?php

namespace App\Services;

use App\Models\DistrictDeliveryRate;
use App\Models\Setting;

class DeliveryFeeService
{
    /**
     * Compute the delivery fee for a given order subtotal and destination district.
     *
     * @param float $subtotal Order items subtotal
     * @param int|null $districtId Destination district ID (for future district overrides)
     * @return float Computed delivery fee
     */
    public function computeDeliveryFee(float $subtotal, ?int $districtId = null): float
    {
        // Check for district-specific override if table populated
        if ($districtId) {
            $districtRate = DistrictDeliveryRate::where('district_id', $districtId)->first();
            if ($districtRate) {
                if ($districtRate->free_delivery_threshold !== null && $subtotal >= (float) $districtRate->free_delivery_threshold) {
                    return 0.00;
                }
                return (float) $districtRate->fee;
            }
        }

        // Global settings configuration
        $flatFee = (float) Setting::get('flat_delivery_fee', 50.00);
        $thresholdRaw = Setting::get('free_delivery_threshold', 2000.00);

        if ($thresholdRaw !== null && $thresholdRaw !== '') {
            $threshold = (float) $thresholdRaw;
            if ($subtotal >= $threshold) {
                return 0.00;
            }
        }

        return $flatFee;
    }
}
