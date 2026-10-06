<?php

namespace App\Http\Resources\V1;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
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
            'variant_label' => $this->variant_label,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'sale_starts_at' => $this->sale_starts_at?->toISOString(),
            'sale_ends_at' => $this->sale_ends_at?->toISOString(),
            'is_on_sale' => (bool) $this->is_on_sale,
            'discount_percentage' => $this->discount_percentage,
            'stock' => (int) $this->stock,
            'low_stock_threshold' => (int) ($this->low_stock_threshold ?? 10),
            'is_default' => (bool) $this->is_default,
        ];
    }
}
