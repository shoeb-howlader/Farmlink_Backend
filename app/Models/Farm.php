<?php

namespace App\Models;

use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'farm_name',
        'farm_type',
        'total_area',
        'pond_count',
        'cultivation_area',
        'division_id',
        'district_id',
        'upazila_id',
        'union_id',
        'pourashava_id',
        'district',
        'upazila',
        'union',
        'village',
        'farm_address',
        'image_url',
        'image_path',
        'image_thumbnail_path',
        'gps_lat',
        'gps_lng',
        'aquaculture_experience_years',
        'previous_farming_experience',
        'main_culture_type',
        'farming_system',
        'main_water_source',
        'water_exchange_facility',
        'water_source_distance',
        'available_facilities',
        'aerator_count',
        'aerator_hp',
        'aerator_hours_per_day',
        'farm_manager',
        'technical_support_used',
        'main_advice_source',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_area' => 'decimal:2',
            'cultivation_area' => 'decimal:2',
            'pond_count' => 'integer',
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'aquaculture_experience_years' => 'integer',
            'aerator_count' => 'integer',
            'aerator_hours_per_day' => 'decimal:1',
            'available_facilities' => 'array',
        ];
    }

    /**
     * Get the user (farmer) that owns the farm.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the farmer that owns the farm (alias for user).
     *
     * @return BelongsTo<User, $this>
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all gallery photos for this farm.
     *
     * @return HasMany<FarmImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(FarmImage::class)->orderBy('sort_order');
    }

    /**
     * Get the vet records for this farm.
     *
     * @return HasMany<VetRecord, $this>
     */
    public function vetRecords(): HasMany
    {
        return $this->hasMany(VetRecord::class);
    }

    /**
     * Get the consultant records for this farm.
     *
     * @return HasMany<ConsultantRecord, $this>
     */
    public function consultantRecords(): HasMany
    {
        return $this->hasMany(ConsultantRecord::class);
    }

    /**
     * Get the orders associated with this specific farm.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'farm_id');
    }

    /**
     * Get the service requests associated with this specific farm.
     *
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'farm_id');
    }

    /**
     * Get the production cycles for this farm.
     *
     * @return HasMany<FarmCycle, $this>
     */
    public function cycles(): HasMany
    {
        return $this->hasMany(FarmCycle::class, 'farm_id')->orderByDesc('start_date');
    }

    /**
     * Get the current active production cycle (end_date is null).
     */
    public function currentCycle()
    {
        return $this->hasOne(FarmCycle::class, 'farm_id')->whereNull('end_date')->latest('start_date');
    }

    /**
     * Get all financial ledger entries for this farm.
     *
     * @return HasMany<FarmLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FarmLedgerEntry::class, 'farm_id')->orderByDesc('entry_date')->orderByDesc('id');
    }

    /**
     * Get the division for this farm.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the district model for this farm.
     */
    public function districtModel(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    /**
     * Get the upazila model for this farm.
     */
    public function upazilaModel(): BelongsTo
    {
        return $this->belongsTo(Upazila::class, 'upazila_id');
    }

    /**
     * Get the union model for this farm.
     */
    public function unionModel(): BelongsTo
    {
        return $this->belongsTo(Union::class, 'union_id');
    }

    /**
     * Get the pourashava model for this farm.
     */
    public function pourashava(): BelongsTo
    {
        return $this->belongsTo(Pourashava::class, 'pourashava_id');
    }
}

