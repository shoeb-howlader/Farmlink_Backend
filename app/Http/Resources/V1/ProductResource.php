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
        $defaultVar = $this->defaultVariant ?? $this->variants->firstWhere('is_default', true) ?? $this->variants->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'price' => (float) $this->price,
            'min_price' => (float) $this->min_price,
            'max_price' => (float) $this->max_price,
            'has_multiple_variants' => (bool) $this->has_multiple_variants,
            'stock' => (int) $this->stock,
            'is_in_stock' => (bool) $this->is_in_stock,
            'low_stock_threshold' => (int) ($this->low_stock_threshold ?? 10),
            'image_url' => $this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : $this->image_url,
            'image_path' => $this->image_path,
            'image_thumbnail_url' => $this->image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_thumbnail_path) : ($this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : $this->image_url),
            'images' => ProductImageResource::collection($this->images),
            'variants' => ProductVariantResource::collection($this->variants),
            'default_variant' => $defaultVar ? new ProductVariantResource($defaultVar) : null,
            'rating_avg' => $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
            'rating_distribution' => $this->rating_distribution,
            'reviews' => ProductReviewResource::collection($this->whenLoaded('reviews')),
            'short_description' => $this->short_description ?: \Illuminate\Support\Str::limit(strip_tags($this->description ?? ''), 155),
            'description' => $this->description,
            'usage_instructions' => $this->usage_instructions,
            'video_url' => $this->video_url,
            'specs' => ProductSpecResource::collection($this->specs),
            'documents' => ProductDocumentResource::collection($this->documents),
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
