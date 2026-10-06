<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistrictDeliveryRate extends Model
{
    protected $fillable = [
        'district_id',
        'fee',
        'free_delivery_threshold',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'free_delivery_threshold' => 'decimal:2',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
