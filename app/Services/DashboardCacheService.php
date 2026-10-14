<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    /**
     * Common date ranges supported by the executive dashboard.
     *
     * @var list<string>
     */
    protected const RANGES = ['all', 'today', 'yesterday', '7d', '30d', 'this_month'];

    /**
     * Flush all dashboard and high-level directory summary caches.
     */
    public static function flush(): void
    {
        foreach (self::RANGES as $range) {
            Cache::forget("admin_dashboard_summary_{$range}");
        }
        Cache::forget('admin_farmers_directory_summary');
        Cache::forget('admin_vet_consultant_records_summary');
    }
}
