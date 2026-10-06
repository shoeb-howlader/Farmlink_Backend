<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pourashava extends Model
{
    protected $fillable = [
        'upazila_id',
        'name',
        'bn_name',
        'url',
    ];

    public function upazila(): BelongsTo
    {
        return $this->belongsTo(Upazila::class);
    }

    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }
}
