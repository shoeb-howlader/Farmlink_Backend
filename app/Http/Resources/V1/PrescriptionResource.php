<?php

namespace App\Http\Resources\V1;

use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Prescription
 */
class PrescriptionResource extends JsonResource
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
            'vet_record_id' => $this->vet_record_id,
            'consultant_record_id' => $this->consultant_record_id,
            'items' => PrescriptionItemResource::collection($this->whenLoaded('items')),
            'pdf_url' => url("/api/v1/prescriptions/{$this->id}/pdf"),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
