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
        $appUrl = config('app.url');

        // If in production or APP_URL is unconfigured/localhost, construct robust full URL
        if (app()->environment('production') || ! $appUrl || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            if (request()?->getSchemeAndHttpHost() && ! str_contains(request()->getSchemeAndHttpHost(), 'localhost') && ! str_contains(request()->getSchemeAndHttpHost(), '127.0.0.1')) {
                return rtrim(request()->getSchemeAndHttpHost(), '/') . '/storage/' . $relativePath;
            }
            if ($appUrl && ! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '127.0.0.1')) {
                return rtrim($appUrl, '/') . '/storage/' . $relativePath;
            }
            return 'https://api.farmlinkcare.com/storage/' . $relativePath;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }
}
