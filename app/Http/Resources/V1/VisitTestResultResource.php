<?php

namespace App\Http\Resources\V1;

use App\Models\VisitTestResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VisitTestResult
 */
class VisitTestResultResource extends JsonResource
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
            'parameter' => $this->parameter,
            'value' => $this->value,
            'unit' => $this->unit,
            'reference_range' => $this->reference_range,
            'flag' => $this->flag,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
