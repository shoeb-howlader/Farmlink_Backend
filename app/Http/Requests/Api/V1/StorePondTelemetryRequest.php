<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePondTelemetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pond_name' => ['nullable', 'string', 'max:100'],
            'recorded_at' => ['nullable', 'date'],
            'dissolved_oxygen' => ['nullable', 'numeric', 'min:0', 'max:25'],
            'ph' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'salinity' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'transparency' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'ammonia' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'alkalinity' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'weather_condition' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sync_source' => ['nullable', 'in:online,offline_sync'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ];
    }
}
