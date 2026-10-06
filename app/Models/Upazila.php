<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Upazila extends Model
{
    protected $fillable = [
        'district_id',
        'name',
        'bn_name',
        'url',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function unions(): HasMany
    {
        return $this->hasMany(Union::class);
    }

    public function pourashavas(): HasMany
    {
        return $this->hasMany(Pourashava::class);
    }

    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }
}
