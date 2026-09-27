<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'phone_verified_at', 'phone_otp', 'phone_otp_expires_at', 'phone_otp_sent_at', 'district', 'gender', 'profile_image_path', 'profile_image_thumbnail_path', 'password', 'is_active', 'status', 'rejection_reason', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'avatar_url', 'last_login_at'])]
#[Hidden(['password', 'remember_token', 'phone_otp'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'phone_otp_expires_at' => 'datetime',
            'phone_otp_sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isApproved(): bool
    {
        if (! $this->hasRole('farmer')) {
            return true;
        }

        return $this->status === 'active';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isPendingVerification(): bool
    {
        return $this->status === 'pending_verification';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function approvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the farms owned by this user (farmer).
     *
     * @return HasMany<Farm, $this>
     */
    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }

    /**
     * Get the orders placed by this user.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the vet records logged by this user (vet doctor).
     *
     * @return HasMany<VetRecord, $this>
     */
    public function vetRecords(): HasMany
    {
        return $this->hasMany(VetRecord::class, 'vet_id');
    }

    /**
     * Get the consultant records logged by this user (consultant).
     *
     * @return HasMany<ConsultantRecord, $this>
     */
    public function consultantRecords(): HasMany
    {
        return $this->hasMany(ConsultantRecord::class, 'consultant_id');
    }

    /**
     * Get all vet records across this farmer's farms.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<VetRecord, Farm, $this>
     */
    public function farmVetRecords(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(VetRecord::class, Farm::class, 'user_id', 'farm_id');
    }

    /**
     * Get all consultant records across this farmer's farms.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<ConsultantRecord, Farm, $this>
     */
    public function farmConsultantRecords(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(ConsultantRecord::class, Farm::class, 'user_id', 'farm_id');
    }

    /**
     * Get service requests submitted by this farmer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'farmer_id');
    }

    /**
     * Get service requests assigned to this practitioner.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ServiceRequest, $this>
     */
    public function assignedServiceRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'assigned_to');
    }
}
