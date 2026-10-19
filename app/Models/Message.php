<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'attachment_path',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected $appends = [
        'attachment_url',
        'is_pdf',
        'is_audio',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        if (str_starts_with($this->attachment_path, 'http://') || str_starts_with($this->attachment_path, 'https://')) {
            return $this->attachment_path;
        }

        $relativePath = ltrim($this->attachment_path, '/');

        // Only in production: ensure we don't accidentally emit localhost URLs if APP_URL is misconfigured
        if (app()->environment('production')) {
            $appUrl = config('app.url');
            if ($appUrl && ! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '127.0.0.1')) {
                return rtrim($appUrl, '/') . '/storage/' . $relativePath;
            }
            if (request()?->getSchemeAndHttpHost() && ! str_contains(request()->getSchemeAndHttpHost(), 'localhost') && ! str_contains(request()->getSchemeAndHttpHost(), '127.0.0.1')) {
                return rtrim(request()->getSchemeAndHttpHost(), '/') . '/storage/' . $relativePath;
            }
            return 'https://api.farmlinkcare.com/storage/' . $relativePath;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }

    public function getIsPdfAttribute(): bool
    {
        if (! $this->attachment_path) {
            return false;
        }

        return strtolower(pathinfo($this->attachment_path, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function getIsAudioAttribute(): bool
    {
        if (! $this->attachment_path) {
            return false;
        }

        $ext = strtolower(pathinfo($this->attachment_path, PATHINFO_EXTENSION));
        return in_array($ext, ['webm', 'mp3', 'ogg', 'wav', 'm4a', 'aac', 'opus', 'mp4']);
    }
}
