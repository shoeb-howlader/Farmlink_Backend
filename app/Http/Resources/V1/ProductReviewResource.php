<?php

namespace App\Http\Resources\V1;

use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductReview
 */
class ProductReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $orderItem = \App\Models\OrderItem::where('order_id', $this->order_id)
            ->where('product_id', $this->product_id)
            ->with('variant')
            ->first();

        $packSize = $orderItem?->variant?->variant_label ?? $orderItem?->variant_label ?? null;
        $district = $this->user?->district;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name ?? 'Verified Farmer',
            'reviewer_district' => $district,
            'order_id' => $this->order_id,
            'variant_label' => $packSize,
            'pack_size' => $packSize,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
