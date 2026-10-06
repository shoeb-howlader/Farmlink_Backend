<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'vet_record_id',
        'consultant_record_id',
    ];

    protected $appends = [
        'pdf_url',
    ];

    public function getPdfUrlAttribute(): string
    {
        return url("/api/v1/prescriptions/{$this->id}/pdf");
    }

    public function vetRecord(): BelongsTo
    {
        return $this->belongsTo(VetRecord::class);
    }

    public function consultantRecord(): BelongsTo
    {
        return $this->belongsTo(ConsultantRecord::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class, 'prescription_id');
    }
}
