<?php

namespace App\Http\Resources\V1;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin ServiceRequest
 */
class ServiceRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photoUrl = null;
        if ($this->photo_path) {
            $photoUrl = str_starts_with($this->photo_path, 'http')
                ? $this->photo_path
                : Storage::disk('public')->url($this->photo_path);
        }

        $turnaroundHours = null;
        if ($this->completed_at && $this->created_at) {
            if ($this->completed_at->greaterThanOrEqualTo($this->created_at)) {
                $turnaroundHours = round($this->created_at->diffInMinutes($this->completed_at) / 60, 1);
            } else {
                \Illuminate\Support\Facades\Log::warning("ServiceRequest #{$this->id} has completed_at before created_at. Negative turnaround excluded.");
            }
        }

        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'farm' => new FarmResource($this->whenLoaded('farm')),
            'farmer_id' => $this->farmer_id,
            'farmer' => new UserResource($this->whenLoaded('farmer')),
            'type' => $this->type,
            'description' => $this->description,
            'urgency' => $this->urgency,
            'photo_url' => $photoUrl,
            'photo_path' => $this->photo_path,
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'sla_threshold_hours' => $this->getSlaThresholdHours(),
            'assigned_to' => $this->assigned_to,
            'assigned_practitioner' => new UserResource($this->whenLoaded('assignedPractitioner')),
            'assigned_at' => $this->assigned_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'turnaround_hours' => $turnaroundHours,
            'fulfilled_record_type' => $this->fulfilled_record_type,
            'fulfilled_record_id' => $this->fulfilled_record_id,
            'fulfilled_record' => $this->whenLoaded('fulfilledRecord'),
            'rating' => $this->rating,
            'feedback_note' => $this->feedback_note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
