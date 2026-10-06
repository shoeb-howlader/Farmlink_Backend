<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FarmImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'image_path',
        'image_thumbnail_path',
        'image_url',
        'caption',
        'category',
        'sort_order',
        'is_primary',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Get the parent farm.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get the public URL for the full image.
     */
    public function getUrlAttribute(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        if (! $this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    /**
     * Get the public URL for the thumbnail image.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->image_thumbnail_path) {
            if (str_starts_with($this->image_thumbnail_path, 'http://') || str_starts_with($this->image_thumbnail_path, 'https://')) {
                return $this->image_thumbnail_path;
            }
            return Storage::disk('public')->url($this->image_thumbnail_path);
        }

        return $this->url;
    }
}
