<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmLedgerEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $canEdit = false;
        $canVoid = false;
        if ($user) {
            $canEdit = $user->can('update', $this->resource);
            $canVoid = $user->can('void', $this->resource);
        }

        $enteredByName = $this->enteredBy?->name ?? 'System';
        $roleLabel = match ($this->entered_by_role) {
            'admin' => 'Admin',
            'deo' => 'DEO Officer',
            'farmer' => 'Farmer',
            default => ucfirst($this->entered_by_role),
        };

        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'cycle_id' => $this->cycle_id,
            'cycle' => $this->whenLoaded('cycle', function () {
                return $this->cycle ? [
                    'id' => $this->cycle->id,
                    'label' => $this->cycle->label,
                    'start_date' => $this->cycle->start_date?->toDateString(),
                    'end_date' => $this->cycle->end_date?->toDateString(),
                    'is_open' => $this->cycle->isOpen(),
                ] : null;
            }),
            'entry_type' => $this->entry_type,
            'category' => $this->category,
            'amount' => (float) $this->amount,
            'entry_date' => $this->entry_date?->toDateString() ?? $this->entry_date,
            'note' => $this->note,
            'photo_url' => $this->photo_url,
            'photo_path' => $this->photo_path,
            'source' => $this->source,
            'source_reference_id' => $this->source_reference_id,
            'source_order' => $this->when($this->source === 'system_order' && $this->relationLoaded('order'), function () {
                return $this->order ? [
                    'id' => $this->order->id,
                    'invoice_number' => $this->order->invoice_number,
                    'status' => $this->order->status,
                    'total' => (float) $this->order->total,
                ] : null;
            }),
            'entered_by' => $this->entered_by,
            'entered_by_name' => $enteredByName,
            'entered_by_role' => $this->entered_by_role,
            'entered_by_display' => "Added by {$enteredByName} ({$roleLabel})",
            'voided_at' => $this->voided_at?->toISOString(),
            'void_reason' => $this->void_reason,
            'is_voided' => $this->isVoided(),
            'can_edit' => $canEdit,
            'can_void' => $canVoid,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
