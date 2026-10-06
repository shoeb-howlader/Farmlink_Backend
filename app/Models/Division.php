<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    protected $fillable = [
        'name',
        'bn_name',
        'url',
    ];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }
}
