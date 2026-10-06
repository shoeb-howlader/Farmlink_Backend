<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'label',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * The farm this cycle belongs to.
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * User who created this cycle.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Explicitly tagged ledger entries for this cycle.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FarmLedgerEntry::class, 'cycle_id');
    }

    /**
     * Check if cycle is ongoing (no end date).
     */
    public function isOpen(): bool
    {
        return $this->end_date === null;
    }
}
