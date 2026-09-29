<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'broadcast_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /**
     * Dispatch an admin or user-specific notification.
     *
     * @param string $type e.g. 'order.placed', 'order.status_updated', 'service_request.assigned'
     * @param string $title
     * @param string $message
     * @param array<string, mixed> $data
     * @param int|null $userId Optional user ID for targeted farmer/staff notifications (null = admin-wide)
     */
    public static function notify(string $type, string $title, string $message, array $data = [], ?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'read_at' => null,
            'created_at' => now(),
        ]);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }
}
