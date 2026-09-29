<?php

namespace App\Http\Resources\V1;

use App\Models\VisitPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
  * @mixin VisitPhoto
  */
class VisitPhotoResource extends JsonResource
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
            'photo_path' => $this->photo_path,
            'url' => $this->url,
            'caption' => $this->caption,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
