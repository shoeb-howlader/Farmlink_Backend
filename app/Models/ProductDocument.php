<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'title',
        'file_path',
        'type', // datasheet, safety, certificate
        'file_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
        ];
    }

    /**
     * Get the direct download/view URL for this document.
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Get a human-readable file size (e.g. "1.2 MB").
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size_bytes) {
            return 'PDF';
        }

        $bytes = $this->file_size_bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1024, 0) . ' KB';
    }

    /**
     * Get the parent product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
