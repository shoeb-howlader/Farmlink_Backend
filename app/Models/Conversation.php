<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'context_type',
        'context_id',
        'status',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the resolved context model instance.
     */
    public function getContextRecord(): ?Model
    {
        if (! $this->context_type || ! $this->context_id) {
            return null;
        }

        return match ($this->context_type) {
            'order' => Order::find($this->context_id),
            'service_request' => ServiceRequest::with(['farm', 'farmer', 'assignedPractitioner'])->find($this->context_id),
            default => null,
        };
    }

    /**
     * Get human-readable context label.
     */
    public function getContextLabel(?\Illuminate\Support\Collection $ordersMap = null, ?\Illuminate\Support\Collection $serviceRequestsMap = null): ?string
    {
        if ($this->type === 'direct') {
            return 'Direct Message';
        }

        if (! $this->context_type || ! $this->context_id) {
            return null;
        }

        if ($this->context_type === 'order') {
            if ($ordersMap && $ordersMap->has($this->context_id)) {
                $order = $ordersMap->get($this->context_id);
                return $order ? "Order #{$order->invoice_number}" : "Order #{$this->context_id}";
            }
            $order = Order::find($this->context_id);
            return $order ? "Order #{$order->invoice_number}" : "Order #{$this->context_id}";
        }

        if ($this->context_type === 'service_request') {
            if ($serviceRequestsMap && $serviceRequestsMap->has($this->context_id)) {
                $req = $serviceRequestsMap->get($this->context_id);
                $typeLabel = $req ? ucfirst(str_replace('_', ' ', $req->type ?? 'Service')) : 'Service';
                return "Service Request #{$this->context_id} ({$typeLabel})";
            }
            $req = ServiceRequest::find($this->context_id);
            $typeLabel = $req ? ucfirst(str_replace('_', ' ', $req->type ?? 'Service')) : 'Service';
            return "Service Request #{$this->context_id} ({$typeLabel})";
        }

        return ucfirst($this->context_type) . " #{$this->context_id}";
    }

    /**
     * Get the display information for the other party relative to the viewer.
     */
    public function getDisplayParty(User $viewer): array
    {
        $isStaff = $viewer->hasAnyRole(['admin', 'data_entry_operator']);
        $users = $this->relationLoaded('users') ? $this->users : $this->users()->with('roles')->get();

        if ($this->type === 'direct') {
            $otherUser = $users->firstWhere('id', '!=', $viewer->id);

            if (! $otherUser && $users->count() >= 2) {
                $u1 = $users[0];
                $u2 = $users[1];
                return [
                    'id' => $u1->id,
                    'name' => "{$u1->name} ↔ {$u2->name}",
                    'phone' => $u1->phone,
                    'role' => 'direct',
                    'role_label' => 'Direct Message',
                    'avatar' => $u1->avatar_url ?? null,
                ];
            }

            $creator = $this->relationLoaded('creator') ? $this->creator : ($this->created_by !== $viewer->id ? $this->creator()->first() : null);
            $target = $otherUser ?? ($this->created_by !== $viewer->id ? $creator : null) ?? $users->first();
            if ($target) {
                $roleName = $target->roles->first()?->name ?? ($target->role ?? 'user');
                $formattedRole = match ($roleName) {
                    'admin' => 'Administrator',
                    'data_entry_operator', 'deo' => 'DEO Staff',
                    'veterinary_doctor' => 'Veterinary Doctor',
                    'consultant' => 'Aquaculture Consultant',
                    'farmer' => 'Farmer',
                    default => ucfirst(str_replace('_', ' ', $roleName)),
                };

                return [
                    'id' => $target->id,
                    'name' => $target->name,
                    'phone' => $target->phone,
                    'role' => $roleName,
                    'role_label' => $formattedRole,
                    'avatar' => $target->avatar_url ?? null,
                ];
            }
        }

        if ($this->type === 'support') {
            if ($isStaff) {
                // Show the farmer for the admin/DEO viewer
                $creator = $this->relationLoaded('creator') ? $this->creator : $this->creator()->first();
                $farmer = $users->first(function ($u) {
                    return ! $u->hasAnyRole(['admin', 'data_entry_operator']);
                }) ?? $creator;

                return [
                    'id' => $farmer?->id,
                    'name' => $farmer?->name ?? 'Farmer',
                    'phone' => $farmer?->phone,
                    'role' => 'farmer',
                    'avatar' => $farmer?->avatar_url ?? null,
                ];
            }

            // For farmer viewer, other party is Support Desk
            return [
                'id' => null,
                'name' => 'Support Desk',
                'phone' => '+880 1700-000000',
                'role' => 'support',
                'avatar' => null,
            ];
        }

        // Practitioner conversation
        if ($this->type === 'practitioner') {
            $isPractitioner = $viewer->hasAnyRole(['veterinary_doctor', 'consultant']);
            $creator = $this->relationLoaded('creator') ? $this->creator : $this->creator()->first();

            if ($isPractitioner) {
                // Viewer is practitioner -> other party is the farmer
                $farmer = $users->first(function ($u) {
                    return ! $u->hasAnyRole(['veterinary_doctor', 'consultant', 'admin', 'data_entry_operator']);
                }) ?? $creator;

                return [
                    'id' => $farmer?->id,
                    'name' => $farmer?->name ?? 'Farmer',
                    'phone' => $farmer?->phone,
                    'role' => 'farmer',
                    'avatar' => $farmer?->avatar_url ?? null,
                ];
            }

            // Find practitioner
            $practitioner = $users->first(function ($u) {
                return $u->hasAnyRole(['veterinary_doctor', 'consultant']);
            });

            if (! $practitioner && $this->context_type === 'service_request') {
                $sr = ServiceRequest::with('assignedPractitioner.roles')->find($this->context_id);
                $practitioner = $sr?->assignedPractitioner;
            }

            $roleName = $practitioner?->roles->first()?->name ?? 'practitioner';
            $formattedRole = match ($roleName) {
                'veterinary_doctor' => 'Veterinary Doctor',
                'consultant' => 'Aquaculture Consultant',
                default => 'Practitioner',
            };

            // If viewer is Admin/DEO staff:
            if ($isStaff) {
                $farmer = $users->first(function ($u) {
                    return ! $u->hasAnyRole(['veterinary_doctor', 'consultant', 'admin', 'data_entry_operator']);
                }) ?? $creator;

                $pName = $practitioner?->name ?? 'Specialist';
                $fName = $farmer?->name ?? 'Farmer';

                return [
                    'id' => $farmer?->id,
                    'name' => "{$fName} ↔ {$pName}",
                    'phone' => $farmer?->phone,
                    'role' => $roleName,
                    'role_label' => "{$formattedRole} Consultation",
                    'avatar' => $farmer?->avatar_url ?? $practitioner?->avatar_url ?? null,
                    'farmer_name' => $fName,
                    'practitioner_name' => $pName,
                ];
            }

            // Viewer is farmer -> other party is the practitioner
            return [
                'id' => $practitioner?->id,
                'name' => $practitioner?->name ?? 'Assigned Practitioner',
                'phone' => $practitioner?->phone,
                'role' => $roleName,
                'role_label' => $formattedRole,
                'avatar' => $practitioner?->avatar_url ?? null,
            ];
        }

        return [
            'id' => null,
            'name' => 'FarmLink Team',
            'role' => 'system',
            'avatar' => null,
        ];
    }

    /**
     * Compute unread message count for a given user.
     */
    public function unreadCountFor(User $user): int
    {
        $participant = $this->relationLoaded('participants')
            ? $this->participants->firstWhere('user_id', $user->id)
            : $this->participants()->where('user_id', $user->id)->first();

        $query = $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at');

        if ($participant?->last_read_at) {
            $query->where('created_at', '>', $participant->last_read_at);
        }

        return $query->count();
    }
}
