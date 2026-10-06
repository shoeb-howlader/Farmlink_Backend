<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RescheduleRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'record_type',
        'record_id',
        'farm_id',
        'farmer_id',
        'practitioner_id',
        'old_date',
        'new_date',
        'reason',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_date' => 'date',
            'new_date' => 'date',
            'reviewed_at' => 'datetime',
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

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getRecordAttribute(): VetRecord|ConsultantRecord|null
    {
        if ($this->record_type === 'vet') {
            return VetRecord::find($this->record_id);
        }

        return ConsultantRecord::find($this->record_id);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
