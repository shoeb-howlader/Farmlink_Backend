<?php

namespace App\Models;

use Database\Factories\ConsultantRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsultantRecord extends Model
{
    /** @use HasFactory<ConsultantRecordFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'consultant_id',
        'visit_date',
        'recommendation',
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
     * Get the farm associated with this consultant record.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get the consultant who conducted the advisory visit.
     *
     * @return BelongsTo<User, $this>
     */
    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
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
     * Get the parent consultant record if this is a follow-up visit.
     *
     * @return BelongsTo<ConsultantRecord, $this>
     */
    public function parentRecord(): BelongsTo
    {
        return $this->belongsTo(ConsultantRecord::class, 'parent_record_id');
    }

    /**
     * Get subsequent follow-up consultant records.
     *
     * @return HasMany<ConsultantRecord, $this>
     */
    public function followUpRecords(): HasMany
    {
        return $this->hasMany(ConsultantRecord::class, 'parent_record_id');
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
