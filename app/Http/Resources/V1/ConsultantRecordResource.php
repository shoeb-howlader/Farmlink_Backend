<?php

namespace App\Http\Resources\V1;

use App\Models\ConsultantRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConsultantRecord
 */
class ConsultantRecordResource extends JsonResource
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
            'farm_id' => $this->farm_id,
            'consultant_id' => $this->consultant_id,
            'consultant' => new UserResource($this->whenLoaded('consultant')),
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'visit_date' => $this->visit_date?->format('Y-m-d'),
            'recommendation' => $this->recommendation,
            'next_follow_up' => $this->next_follow_up?->format('Y-m-d'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
