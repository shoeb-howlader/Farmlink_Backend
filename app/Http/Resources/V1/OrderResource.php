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
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'delivery_address' => $this->delivery_address,
            'division_id' => $this->division_id,
            'district_id' => $this->district_id,
            'upazila_id' => $this->upazila_id,
            'union_id' => $this->union_id,
            'pourashava_id' => $this->pourashava_id,
            'district' => $this->district,
            'upazila' => $this->upazila,
            'union' => $this->union,
            'division' => $this->whenLoaded('division'),
            'district_data' => $this->whenLoaded('districtData'),
            'upazila_data' => $this->whenLoaded('upazilaData'),
            'union_data' => $this->whenLoaded('unionData'),
            'pourashava_data' => $this->whenLoaded('pourashavaData'),
            'status' => $this->status,
            'channel' => $this->channel ?? 'self_service',
            'payment_mode' => $this->payment_mode ?? 'cod',
            'payment_status' => $this->payment_status,
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'sub_method' => $this->sub_method,
            'bank_tran_id' => $this->bank_tran_id,
            'payment_id' => $this->relationLoaded('payment') ? $this->payment?->id : ($this->payment_id ?? null),
            'paid_at' => $this->paid_at?->toISOString(),
            'refund_status' => $this->refund_status ?? 'none',
            'refund_reference' => $this->refund_reference,
            'refund_amount' => $this->refund_amount !== null ? (float) $this->refund_amount : null,
            'refunded_at' => $this->refunded_at?->toISOString(),
            'refund_reason' => $this->refund_reason,
            'notes' => $this->notes,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'ip_country' => $this->ip_country ?? 'BD',
            'ip_city' => $this->ip_city,
            'ip_region' => $this->ip_region,
            'ip_isp' => $this->ip_isp,
            'advance_delivery_fee' => (float) ($this->advance_delivery_fee ?? 0.00),
            'advance_paid_at' => $this->advance_paid_at?->toISOString(),
            'risk_score' => (int) ($this->risk_score ?? 0),
            'risk_level' => $this->risk_level ?? 'low',
            'is_flagged' => (bool) ($this->is_flagged ?? false),
            'flag_reasons' => $this->flag_reasons ?? [],
            'verification_status' => $this->verification_status ?? 'unverified',
            'verified_at' => $this->verified_at?->toISOString(),
            'verified_by' => $this->verified_by,
            'subtotal' => (float) ($this->subtotal ?? $this->total),
            'delivery_fee' => (float) ($this->delivery_fee ?? 0.00),
            'discount_amount' => (float) ($this->discount_amount ?? 0.00),
            'coupon_id' => $this->coupon_id,
            'coupon' => $this->whenLoaded('coupon', function () {
                return $this->coupon ? [
                    'id' => $this->coupon->id,
                    'code' => $this->coupon->code,
                    'type' => $this->coupon->type,
                    'value' => (float) $this->coupon->value,
                ] : null;
            }),
            'total' => (float) $this->total,
            'customer_24h_orders_count' => $this->customer_24h_orders_count ?? null,
            'recent_attempts' => $this->when($this->relationLoaded('recent_attempts'), function () {
                return $this->recent_attempts->map(function ($o) {
                    return [
                        'id' => $o->id,
                        'invoice_number' => $o->invoice_number,
                        'total' => (float) $o->total,
                        'payment_mode' => $o->payment_mode,
                        'sub_method' => $o->sub_method,
                        'status' => $o->status,
                        'payment_status' => $o->payment_status,
                        'gateway_transaction_id' => $o->gateway_transaction_id,
                        'created_at' => $o->created_at?->toISOString(),
                        'paid_at' => $o->paid_at?->toISOString(),
                    ];
                });
            }),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
