<?php

namespace App\Http\Resources\V1;

use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class RefundResource extends JsonResource
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
            'payment_id' => $this->payment_id,
            'order_id' => $this->order_id,
            'amount' => (float) $this->amount,
            'reason' => $this->reason,
            'status' => $this->status,
            'restock_items' => (bool) $this->restock_items,
            'gateway_refund_ref' => $this->gateway_refund_ref,
            'gateway_response' => $this->gateway_response,
            'initiated_by' => $this->initiated_by,
            'initiator' => $this->whenLoaded('initiator', function () {
                return $this->initiator ? [
                    'id' => $this->initiator->id,
                    'name' => $this->initiator->name,
                    'email' => $this->initiator->email,
                ] : null;
            }),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
