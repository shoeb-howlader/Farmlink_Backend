<?php

namespace App\Http\Resources\V1;

use App\Models\PondTelemetry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PondTelemetry
 */
class PondTelemetryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $evaluation = $this->evaluateHealthAlerts();

        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'user_id' => $this->user_id,
            'recorder_name' => $this->user?->name,
            'pond_name' => $this->pond_name,
            'recorded_at' => $this->recorded_at?->toISOString(),
            'dissolved_oxygen' => $this->dissolved_oxygen,
            'ph' => $this->ph,
            'salinity' => $this->salinity,
            'temperature' => $this->temperature,
            'transparency' => $this->transparency,
            'ammonia' => $this->ammonia,
            'alkalinity' => $this->alkalinity,
            'weather_condition' => $this->weather_condition,
            'notes' => $this->notes,
            'sync_source' => $this->sync_source,
            'client_uuid' => $this->client_uuid,
            'health_status' => $evaluation['status'],
            'alerts' => $evaluation['alerts'],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
