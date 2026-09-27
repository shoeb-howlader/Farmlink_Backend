<?php

namespace App\Http\Resources\V1;

use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Farm
 */
class FarmResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'farm_name' => $this->farm_name,
            'image_url' => $this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : $this->image_url,
            'image_path' => $this->image_path,
            'image_thumbnail_url' => $this->image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_thumbnail_path) : ($this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : $this->image_url),
            'farm_type' => $this->farm_type,
            'total_area' => (float) $this->total_area,
            'pond_count' => (int) $this->pond_count,
            'cultivation_area' => $this->cultivation_area !== null ? (float) $this->cultivation_area : null,
            'district' => $this->district,
            'upazila' => $this->upazila,
            'union' => $this->union,
            'village' => $this->village,
            'farm_address' => $this->farm_address,
            'gps_lat' => $this->gps_lat !== null ? (float) $this->gps_lat : null,
            'gps_lng' => $this->gps_lng !== null ? (float) $this->gps_lng : null,
            'aquaculture_experience_years' => $this->aquaculture_experience_years,
            'previous_farming_experience' => $this->previous_farming_experience,
            'main_culture_type' => $this->main_culture_type,
            'farming_system' => $this->farming_system,
            'main_water_source' => $this->main_water_source,
            'water_exchange_facility' => $this->water_exchange_facility,
            'water_source_distance' => $this->water_source_distance,
            'available_facilities' => $this->available_facilities,
            'aerator_count' => $this->aerator_count,
            'aerator_hp' => $this->aerator_hp,
            'aerator_hours_per_day' => $this->aerator_hours_per_day !== null ? (float) $this->aerator_hours_per_day : null,
            'farm_manager' => $this->farm_manager,
            'technical_support_used' => $this->technical_support_used,
            'main_advice_source' => $this->main_advice_source,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'visits_count' => (int) (($this->vet_records_count ?? 0) + ($this->consultant_records_count ?? 0)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
