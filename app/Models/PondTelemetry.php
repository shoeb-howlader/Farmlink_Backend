<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PondTelemetry extends Model
{
    use HasFactory;

    protected $fillable = [
        'farm_id',
        'user_id',
        'pond_name',
        'recorded_at',
        'dissolved_oxygen',
        'ph',
        'salinity',
        'temperature',
        'transparency',
        'ammonia',
        'alkalinity',
        'weather_condition',
        'notes',
        'sync_source',
        'client_uuid',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'dissolved_oxygen' => 'float',
        'ph' => 'float',
        'salinity' => 'float',
        'temperature' => 'float',
        'transparency' => 'float',
        'ammonia' => 'float',
        'alkalinity' => 'float',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Evaluate water parameter health status and actionable advisory hints.
     *
     * @return array<string, mixed>
     */
    public function evaluateHealthAlerts(): array
    {
        $alerts = [];
        $overallStatus = 'normal';

        // 1. Dissolved Oxygen (DO in mg/L)
        if ($this->dissolved_oxygen !== null) {
            if ($this->dissolved_oxygen < 3.5) {
                $alerts[] = [
                    'parameter' => 'dissolved_oxygen',
                    'level' => 'critical',
                    'message_en' => 'Critical low oxygen! Turn on all aerators immediately and avoid feeding.',
                    'message_bn' => 'মারাত্মকভাবে কম অক্সিজেন! দ্রুত সকল এয়ারেটর চালু করুন এবং খাবার দেওয়া বন্ধ রাখুন।',
                ];
                $overallStatus = 'critical';
            } elseif ($this->dissolved_oxygen < 4.5) {
                $alerts[] = [
                    'parameter' => 'dissolved_oxygen',
                    'level' => 'warning',
                    'message_en' => 'Suboptimal oxygen. Run aerators during early morning hours.',
                    'message_bn' => 'অক্সিজেন কিছুটা কম। ভোরের দিকে এয়ারেটর অতিরিক্ত সময় চালু রাখুন।',
                ];
                if ($overallStatus !== 'critical') {
                    $overallStatus = 'warning';
                }
            }
        }

        // 2. pH Level
        if ($this->ph !== null) {
            if ($this->ph < 7.0 || $this->ph > 9.0) {
                $alerts[] = [
                    'parameter' => 'ph',
                    'level' => 'critical',
                    'message_en' => 'pH outside safe aquaculture range (7.5 - 8.5). Apply lime or water exchange.',
                    'message_bn' => 'পিএইচ মাত্রা নিরাপদ সীমার (৭.৫ - ৮.৫) বাইরে। চুন প্রয়োগ বা পানি পরিবর্তনের ব্যবস্থা নিন।',
                ];
                $overallStatus = 'critical';
            } elseif ($this->ph < 7.3 || $this->ph > 8.7) {
                $alerts[] = [
                    'parameter' => 'ph',
                    'level' => 'warning',
                    'message_en' => 'pH fluctuating. Monitor closely before feeding.',
                    'message_bn' => 'পিএইচ ওঠানামা করছে। খাবার দেওয়ার পূর্বে নিবিড়ভাবে পর্যবেক্ষণ করুন।',
                ];
                if ($overallStatus !== 'critical') {
                    $overallStatus = 'warning';
                }
            }
        }

        // 3. Ammonia (mg/L)
        if ($this->ammonia !== null && $this->ammonia > 0.1) {
            $alerts[] = [
                'parameter' => 'ammonia',
                'level' => 'critical',
                'message_en' => 'Toxic ammonia level! Stop feeding and apply yucca extract/zeolite gas binder.',
                'message_bn' => 'অ্যামোনিয়া বিষাক্ত মাত্রায় পৌঁছেছে! খাবার বন্ধ করুন এবং ইউকা/জিওলাইট গ্যাস বাইন্ডার দিন।',
            ];
            $overallStatus = 'critical';
        }

        return [
            'status' => $overallStatus,
            'alerts' => $alerts,
        ];
    }
}
