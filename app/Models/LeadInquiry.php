<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'topic',
        'message',
        'source',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
