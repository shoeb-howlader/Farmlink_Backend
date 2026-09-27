<?php

namespace App\Http\Resources\V1;

use App\Models\VetRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VetRecord
 */
class VetRecordResource extends JsonResource
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
            'vet_id' => $this->vet_id,
            'vet' => new UserResource($this->whenLoaded('vet')),
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'visit_date' => $this->visit_date?->format('Y-m-d'),
            'findings' => $this->findings,
            'treatment' => $this->treatment,
            'medicine_given' => $this->medicine_given,
            'next_follow_up' => $this->next_follow_up?->format('Y-m-d'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
