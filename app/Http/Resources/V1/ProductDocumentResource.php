<?php

namespace App\Http\Resources\V1;

use App\Models\ProductDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductDocument
 */
class ProductDocumentResource extends JsonResource
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
            'product_id' => $this->product_id,
            'title' => $this->title,
            'file_path' => $this->file_path,
            'url' => $this->url,
            'type' => $this->type,
            'file_size_bytes' => $this->file_size_bytes,
            'formatted_size' => $this->formatted_file_size,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
