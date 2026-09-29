<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ServiceRequest extends Model
{
    use HasFactory;

    public const SLA_URGENT_PENDING_HOURS = 24;
    public const SLA_NORMAL_PENDING_HOURS = 72;

    protected $fillable = [
        'farm_id',
        'farmer_id',
        'type',
        'description',
        'urgency',
        'photo_path',
        'status',
        'source_channel',
        'parent_record_type',
        'parent_record_id',
        'assigned_to',
        'assigned_at',
        'completed_at',
        'fulfilled_record_type',
        'fulfilled_record_id',
        'rating',
        'feedback_note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function assignedPractitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function fulfilledRecord(): MorphTo
    {
        return $this->morphTo(null, 'fulfilled_record_type', 'fulfilled_record_id');
    }

    public function parentRecord(): MorphTo
    {
        return $this->morphTo(null, 'parent_record_type', 'parent_record_id');
    }

    /**
     * Scope query to sort urgent requests first, then by oldest creation.
     */
    public function scopeUrgentFirst(Builder $query): Builder
    {
        return $query->orderByRaw("CASE WHEN urgency = 'urgent' THEN 0 ELSE 1 END")->oldest();
    }

    /**
     * Scope query to open requests (pending, assigned, in_progress).
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'assigned', 'in_progress']);
    }

    /**
     * Determine if a pending service request has exceeded its SLA response threshold.
     */
    public function isOverdue(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $threshold = $this->getSlaThresholdHours();

        return $this->created_at ? $this->created_at->copy()->addHours($threshold)->isPast() : false;
    }

    /**
     * Get the SLA threshold in hours based on urgency.
     */
    public function getSlaThresholdHours(): int
    {
        return $this->urgency === 'urgent'
            ? self::SLA_URGENT_PENDING_HOURS
            : self::SLA_NORMAL_PENDING_HOURS;
    }
}
