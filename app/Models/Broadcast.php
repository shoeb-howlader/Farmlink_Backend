<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'target_audience',
        'target_district',
        'target_role',
        'target_user_ids',
        'sent_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_user_ids' => 'array',
            'sent_count' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AdminNotification::class, 'broadcast_id');
    }

    public function getReadCountAttribute(): int
    {
        return $this->notifications()->whereNotNull('read_at')->count();
    }
}
