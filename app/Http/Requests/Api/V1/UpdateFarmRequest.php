<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFarmRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'farm_name' => ['sometimes', 'required', 'string', 'max:255'],
            'farm_type' => ['sometimes', 'required', 'string', 'max:100'],
            'total_area' => ['sometimes', 'required', 'numeric', 'min:0'],
            'pond_count' => ['nullable', 'integer', 'min:1'],
            'cultivation_area' => ['nullable', 'numeric', 'min:0'],
            'district' => ['sometimes', 'required', 'string', 'max:100'],
            'upazila' => ['sometimes', 'required', 'string', 'max:100'],
            'union' => ['nullable', 'string', 'max:100'],
            'village' => ['nullable', 'string', 'max:100'],
            'farm_address' => ['nullable', 'string'],
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'aquaculture_experience_years' => ['nullable', 'integer', 'min:0'],
            'previous_farming_experience' => ['nullable', 'string', 'max:100'],
            'main_culture_type' => ['sometimes', 'required', 'string', 'max:100'],
            'farming_system' => ['sometimes', 'required', 'string', 'max:100'],
            'main_water_source' => ['nullable', 'string', 'max:100'],
            'water_exchange_facility' => ['nullable', 'string', 'max:100'],
            'water_source_distance' => ['nullable', 'string', 'max:100'],
            'available_facilities' => ['nullable', 'array'],
            'available_facilities.*' => ['string'],
            'aerator_count' => ['nullable', 'integer', 'min:0'],
            'aerator_hp' => ['nullable', 'string', 'max:50'],
            'aerator_hours_per_day' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'farm_manager' => ['nullable', 'string', 'max:100'],
            'technical_support_used' => ['nullable', 'string', 'max:100'],
            'main_advice_source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
