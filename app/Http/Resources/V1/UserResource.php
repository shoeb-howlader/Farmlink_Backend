<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'phone' => $this->phone,
            'phone_verified' => $this->phone_verified_at !== null,
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'email' => $this->email,
            'district' => $this->district,
            'gender' => $this->gender ?? 'unspecified',
            'profile_image_path' => $this->profile_image_path,
            'role' => $this->roles->first()?->name ?? 'farmer',
            'roles' => $this->roles->pluck('name'),
            'is_active' => (bool) ($this->is_active ?? true),
            'status' => $this->status ?? 'active',
            'rejection_reason' => $this->rejection_reason,
            'cod_blocked' => (bool) ($this->cod_blocked ?? false),
            'consultations_blocked' => (bool) ($this->consultations_blocked ?? false),
            'is_blacklisted' => (bool) ($this->is_blacklisted ?? false),
            'delivered_orders_count' => (int) ($this->delivered_orders_count ?? 0),
            'refused_cod_orders_count' => (int) ($this->refused_cod_orders_count ?? 0),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'avatar_url' => $this->profile_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->profile_image_path) : $this->avatar_url,
            'avatar_thumbnail_url' => $this->profile_image_thumbnail_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->profile_image_thumbnail_path) : ($this->profile_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->profile_image_path) : $this->avatar_url),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'permissions' => ($this->relationLoaded('permissions') || ($request->user()?->id === $this->id))
                ? $this->getAllPermissions()->pluck('name')
                : collect(),
            'abilities' => ($this->relationLoaded('permissions') || ($request->user()?->id === $this->id))
                ? $this->getAllPermissions()->pluck('name')
                : collect(),
            'farms' => FarmResource::collection($this->whenLoaded('farms')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
