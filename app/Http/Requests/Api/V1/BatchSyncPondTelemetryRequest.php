<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BatchSyncPondTelemetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.client_uuid' => ['required', 'string', 'max:64'],
            'items.*.pond_name' => ['nullable', 'string', 'max:100'],
            'items.*.recorded_at' => ['required', 'date'],
            'items.*.dissolved_oxygen' => ['nullable', 'numeric', 'min:0', 'max:25'],
            'items.*.ph' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'items.*.salinity' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'items.*.temperature' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'items.*.transparency' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'items.*.ammonia' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'items.*.alkalinity' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'items.*.weather_condition' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
