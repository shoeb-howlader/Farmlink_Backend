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
            'division_id' => $this->division_id,
            'district_id' => $this->district_id,
            'upazila_id' => $this->upazila_id,
            'union_id' => $this->union_id,
            'pourashava_id' => $this->pourashava_id,
            'division' => $this->relationLoaded('division') && $this->division ? [
                'id' => $this->division->id,
                'name' => $this->division->name,
                'bn_name' => $this->division->bn_name,
            ] : null,
            'district_data' => $this->relationLoaded('districtModel') && $this->districtModel ? [
                'id' => $this->districtModel->id,
                'name' => $this->districtModel->name,
                'bn_name' => $this->districtModel->bn_name,
            ] : null,
            'upazila_data' => $this->relationLoaded('upazilaModel') && $this->upazilaModel ? [
                'id' => $this->upazilaModel->id,
                'name' => $this->upazilaModel->name,
                'bn_name' => $this->upazilaModel->bn_name,
            ] : null,
            'union_data' => $this->relationLoaded('unionModel') && $this->unionModel ? [
                'id' => $this->unionModel->id,
                'name' => $this->unionModel->name,
                'bn_name' => $this->unionModel->bn_name,
            ] : null,
            'pourashava_data' => $this->relationLoaded('pourashava') && $this->pourashava ? [
                'id' => $this->pourashava->id,
                'name' => $this->pourashava->name,
                'bn_name' => $this->pourashava->bn_name,
            ] : null,
            'has_unmatched_location' => !($this->district_id && $this->upazila_id && ($this->union_id || $this->pourashava_id || empty($this->union))),
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
            'images' => ($this->relationLoaded('images') && $this->images) ? $this->images->map(fn ($img) => [
                'id' => $img->id,
                'farm_id' => $img->farm_id,
                'image_path' => $img->image_path,
                'image_url' => $img->url,
                'image_thumbnail_url' => $img->thumbnail_url,
                'caption' => $img->caption,
                'category' => $img->category ?? 'general',
                'sort_order' => (int) $img->sort_order,
                'is_primary' => (bool) $img->is_primary,
                'created_at' => $img->created_at?->toISOString(),
            ]) : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
