<?php

namespace App\Http\Resources\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'user_id' => $this->user_id,
            'farm_id' => $this->farm_id,
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'user' => new UserResource($this->whenLoaded('user')),
            'status' => $this->status,
            'channel' => $this->channel ?? 'self_service',
            'payment_mode' => $this->payment_mode ?? 'cod',
            'notes' => $this->notes,
            'total' => (float) $this->total,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
