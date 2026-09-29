<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VisitPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'vet_record_id',
        'consultant_record_id',
        'photo_path',
        'caption',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'url',
    ];

    public function getUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        if (str_starts_with($this->photo_path, 'http://') || str_starts_with($this->photo_path, 'https://')) {
            return $this->photo_path;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    public function vetRecord(): BelongsTo
    {
        return $this->belongsTo(VetRecord::class);
    }

    public function consultantRecord(): BelongsTo
    {
        return $this->belongsTo(ConsultantRecord::class);
    }
}
