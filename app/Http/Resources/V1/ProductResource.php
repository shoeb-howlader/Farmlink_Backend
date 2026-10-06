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
        $defaultVar = $this->relationLoaded('variants')
            ? ($this->variants->firstWhere('is_default', true) ?? $this->variants->first())
            : ($this->relationLoaded('defaultVariant') ? $this->defaultVariant : null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'price' => (float) $this->price,
            'compare_at_price' => ($defaultVar && $defaultVar->is_on_sale && $defaultVar->compare_at_price !== null) ? (float) $defaultVar->compare_at_price : null,
            'is_on_sale' => (bool) ($defaultVar?->is_on_sale ?? false),
            'discount_percentage' => $defaultVar?->discount_percentage ?? null,
            'min_price' => (float) $this->min_price,
            'max_price' => (float) $this->max_price,
            'has_multiple_variants' => (bool) $this->has_multiple_variants,
            'stock' => (int) $this->stock,
            'is_in_stock' => (bool) $this->is_in_stock,
            'low_stock_threshold' => (int) ($this->low_stock_threshold ?? 10),
            'image_url' => $this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : $this->image_url,
            'image_path' => $this->image_path,
            'image_thumbnail_url' => $this->image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_thumbnail_path) : ($this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : $this->image_url),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'default_variant' => $defaultVar ? new ProductVariantResource($defaultVar) : null,
            'rating_avg' => $this->reviews_avg_rating !== null
                ? round((float) $this->reviews_avg_rating, 1)
                : ($this->relationLoaded('reviews') || $request->routeIs('*products.show') ? $this->rating_avg : null),
            'rating_count' => (int) ($this->reviews_count ?? ($this->relationLoaded('reviews') || $request->routeIs('*products.show') ? $this->rating_count : 0)),
            'rating_distribution' => $this->when(
                $request->routeIs('*products.show') || $this->relationLoaded('reviews'),
                fn () => $this->rating_distribution,
                (object) ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0]
            ),
            'reviews' => ProductReviewResource::collection($this->whenLoaded('reviews')),
            'short_description' => $this->short_description ?: \Illuminate\Support\Str::limit(strip_tags($this->description ?? ''), 155),
            'description' => $this->description,
            'usage_instructions' => $this->usage_instructions,
            'video_url' => $this->video_url,
            'specs' => ProductSpecResource::collection($this->whenLoaded('specs')),
            'documents' => ProductDocumentResource::collection($this->whenLoaded('documents')),
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
