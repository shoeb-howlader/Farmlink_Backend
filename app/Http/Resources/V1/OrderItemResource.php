<?php

namespace App\Http\Resources\V1;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $price = (float) ($this->price_at_purchase ?? $this->price);
        $review = $this->relationLoaded('review')
            ? $this->review
            : ($request->routeIs('*orders.show')
                ? \App\Models\ProductReview::where('order_id', $this->order_id)
                    ->where('product_id', $this->product_id)
                    ->first()
                : null);

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'variant' => ($this->relationLoaded('variant') && $this->variant) ? new ProductVariantResource($this->variant) : null,
            'variant_label' => $this->relationLoaded('variant') ? $this->variant?->variant_label : null,
            'variant_label_bn' => $this->relationLoaded('variant') ? $this->variant?->variant_label_bn : null,
            'quantity' => (int) $this->quantity,
            'price' => $price,
            'price_at_purchase' => $price,
            'total' => round($price * $this->quantity, 2),
            'has_reviewed' => $review !== null,
            'review' => $review ? new ProductReviewResource($review) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
