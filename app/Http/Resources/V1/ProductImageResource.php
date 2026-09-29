<?php

namespace App\Http\Resources\V1;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductImage
 */
class ProductImageResource extends JsonResource
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
            'image_path' => $this->image_path,
            'alt_text' => $this->alt_text,
            'url' => $this->url,
            'sort_order' => (int) $this->sort_order,
            'is_primary' => (bool) $this->is_primary,
        ];
    }
}
