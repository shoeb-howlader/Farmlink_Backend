<?php

namespace App\Http\Resources\V1;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
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
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'method' => $this->method,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'sub_method' => $this->sub_method,
            'card_type' => $this->card_type,
            'card_brand' => $this->card_brand,
            'card_issuer' => $this->card_issuer,
            'bank_tran_id' => $this->bank_tran_id,
            'masked_account' => $this->masked_account,
            'gateway_response' => $this->gateway_response,
            'paid_at' => $this->paid_at?->toISOString(),
            'notes' => $this->notes,
            'refunded_amount' => (float) $this->refunded_amount,
            'remaining_refundable_amount' => (float) $this->remaining_refundable_amount,
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'phone' => $this->user->phone,
                    'email' => $this->user->email,
                    'district' => $this->user->district,
                ] : null;
            }),
            'order' => $this->whenLoaded('order', function () {
                return $this->order ? (new OrderResource($this->order))->resolve() : null;
            }),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
