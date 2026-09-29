<?php

namespace App\Models;

use Database\Factories\VetRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VetRecord extends Model
{
    /** @use HasFactory<VetRecordFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'vet_id',
        'visit_date',
        'findings',
        'treatment',
        'medicine_given',
        'next_follow_up',
        'parent_record_id',
        'follow_up_service_request_id',
        'lead_reminder_sent_at',
        'overdue_reminder_sent_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'next_follow_up' => 'date',
            'lead_reminder_sent_at' => 'datetime',
            'overdue_reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * Get the farm associated with this vet record.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get the veterinary doctor who conducted the visit.
     *
     * @return BelongsTo<User, $this>
     */
    public function vet(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vet_id');
    }

    /**
     * Get the structured prescription associated with this record.
     *
     * @return HasOne<Prescription, $this>
     */
    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    /**
     * Get the parent vet record if this is a follow-up visit.
     *
     * @return BelongsTo<VetRecord, $this>
     */
    public function parentRecord(): BelongsTo
    {
        return $this->belongsTo(VetRecord::class, 'parent_record_id');
    }

    /**
     * Get subsequent follow-up vet records.
     *
     * @return HasMany<VetRecord, $this>
     */
    public function followUpRecords(): HasMany
    {
        return $this->hasMany(VetRecord::class, 'parent_record_id');
    }

    /**
     * Get the automated follow-up service request generated from this record.
     *
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function followUpServiceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'follow_up_service_request_id');
    }

    /**
     * Get the structured test results / readings recorded during this visit.
     *
     * @return HasMany<VisitTestResult, $this>
     */
    public function testResults(): HasMany
    {
        return $this->hasMany(VisitTestResult::class);
    }

    /**
     * Get the photos attached to this visit.
     *
     * @return HasMany<VisitPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(VisitPhoto::class)->orderBy('sort_order')->orderBy('id');
    }
}
