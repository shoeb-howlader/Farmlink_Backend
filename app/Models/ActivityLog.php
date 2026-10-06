<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'changes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Record an audit activity log entry.
     *
     * @param string $action E.g. 'order.status_changed', 'farm.created', 'staff.deactivated'
     * @param Model|string|null $subject The model or class being acted upon
     * @param array<string, mixed>|null $changes Before/after details or context
     * @param User|int|null $actor The user performing the action (defaults to auth user)
     * @param int|string|null $subjectId Optional subject ID if $subject is a class string
     */
    public static function log(
        string $action,
        Model|string|null $subject = null,
        ?array $changes = null,
        User|int|null $actor = null,
        int|string|null $subjectId = null
    ): self {
        $actorId = $actor instanceof User ? $actor->id : ($actor ?? auth()->id());
        $subjectType = $subject instanceof Model ? get_class($subject) : $subject;
        $finalSubjectId = $subject instanceof Model ? $subject->getKey() : ($subjectId ?? $changes['id'] ?? null);

        return self::create([
            'user_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $finalSubjectId,
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }
}
