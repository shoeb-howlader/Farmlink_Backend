<?php

namespace App\Http\Resources\V1;

use App\Models\ProductSpec;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductSpec
 */
class ProductSpecResource extends JsonResource
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
            'label' => $this->label,
            'value' => $this->value,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
