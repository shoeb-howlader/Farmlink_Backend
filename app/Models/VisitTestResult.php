<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'vet_record_id',
        'consultant_record_id',
        'parameter',
        'value',
        'unit',
        'reference_range',
        'flag',
    ];

    public function vetRecord(): BelongsTo
    {
        return $this->belongsTo(VetRecord::class);
    }

    public function consultantRecord(): BelongsTo
    {
        return $this->belongsTo(ConsultantRecord::class);
    }
}
