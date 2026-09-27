<?php

namespace App\Http\Resources\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'name' => $this->name,
            'category' => $this->category,
            'price' => (float) $this->price,
            'stock' => (int) $this->stock,
            'low_stock_threshold' => (int) ($this->low_stock_threshold ?? 10),
            'image_url' => $this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : $this->image_url,
            'image_path' => $this->image_path,
            'image_thumbnail_url' => $this->image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_thumbnail_path) : ($this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : $this->image_url),
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
