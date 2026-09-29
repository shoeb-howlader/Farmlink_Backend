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
            'stock' => (int) $this->stock,
            'low_stock_threshold' => (int) ($this->low_stock_threshold ?? 10),
            'is_default' => (bool) $this->is_default,
        ];
    }
}
