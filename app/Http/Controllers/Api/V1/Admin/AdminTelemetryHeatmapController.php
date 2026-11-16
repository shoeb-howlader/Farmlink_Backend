<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminTelemetryHeatmapController extends ApiController
{
    /**
     * Display aggregated district and upazila water quality heatmap data
     * for Fisheries Department and Administrative oversight.
     *
     * Optimized with database-level SQL aggregation and short-term caching
     * to eliminate memory exhaustion and 504 timeouts under high load.
     */
    public function index(Request $request): JsonResponse
    {
        $days = max(1, min((int) ($request->query('days') ?? 14), 90));
        $cacheKey = "admin_telemetry_district_heatmap_{$days}";

        $data = Cache::remember($cacheKey, 300, function () use ($days) {
            $since = Carbon::now()->subDays($days);

            // 1. Fetch district-level aggregates directly via SQL
            $districtRows = DB::table('pond_telemetries')
                ->join('farms', 'farms.id', '=', 'pond_telemetries.farm_id')
                ->where('pond_telemetries.recorded_at', '>=', $since)
                ->selectRaw("
                    COALESCE(NULLIF(TRIM(farms.district), ''), 'Other / Unassigned') as district_name,
                    COUNT(DISTINCT pond_telemetries.farm_id) as farms_count,
                    COUNT(pond_telemetries.id) as total_readings,
                    AVG(pond_telemetries.dissolved_oxygen) as avg_do,
                    MIN(pond_telemetries.dissolved_oxygen) as min_do,
                    AVG(pond_telemetries.ph) as avg_ph,
                    AVG(pond_telemetries.salinity) as avg_salinity,
                    AVG(pond_telemetries.temperature) as avg_temperature,
                    SUM(CASE WHEN pond_telemetries.dissolved_oxygen IS NOT NULL AND pond_telemetries.dissolved_oxygen < 3.5 THEN 1 ELSE 0 END) as hypoxia_count,
                    SUM(CASE WHEN pond_telemetries.ph IS NOT NULL AND (pond_telemetries.ph < 7.0 OR pond_telemetries.ph > 9.0) THEN 1 ELSE 0 END) as abnormal_ph_count,
                    SUM(CASE WHEN pond_telemetries.ammonia IS NOT NULL AND pond_telemetries.ammonia > 0.1 THEN 1 ELSE 0 END) as toxic_ammonia_count
                ")
                ->groupByRaw("COALESCE(NULLIF(TRIM(farms.district), ''), 'Other / Unassigned')")
                ->get();

            if ($districtRows->isEmpty()) {
                return [
                    'time_window_days' => $days,
                    'summary_kpis' => [
                        'monitored_districts_count' => 0,
                        'total_farms_monitored' => 0,
                        'total_readings_window' => 0,
                        'total_hypoxia_alerts' => 0,
                        'critical_districts_count' => 0,
                        'safe_water_compliance_percent' => 100.0,
                    ],
                    'districts' => [],
                ];
            }

            // 2. Fetch upazila-level aggregates directly via SQL
            $upazilaRows = DB::table('pond_telemetries')
                ->join('farms', 'farms.id', '=', 'pond_telemetries.farm_id')
                ->where('pond_telemetries.recorded_at', '>=', $since)
                ->selectRaw("
                    COALESCE(NULLIF(TRIM(farms.district), ''), 'Other / Unassigned') as district_name,
                    COALESCE(NULLIF(TRIM(farms.upazila), ''), 'General') as upazila_name,
                    COUNT(DISTINCT pond_telemetries.farm_id) as farms_count,
                    COUNT(pond_telemetries.id) as total_readings,
                    AVG(pond_telemetries.dissolved_oxygen) as avg_do,
                    SUM(CASE WHEN pond_telemetries.dissolved_oxygen IS NOT NULL AND pond_telemetries.dissolved_oxygen < 3.5 THEN 1 ELSE 0 END) as hypoxia_count
                ")
                ->groupByRaw("
                    COALESCE(NULLIF(TRIM(farms.district), ''), 'Other / Unassigned'),
                    COALESCE(NULLIF(TRIM(farms.upazila), ''), 'General')
                ")
                ->get()
                ->groupBy('district_name');

            // 3. Fetch recent critical alerts (max 200 recent rows total, avoiding loading all models into RAM)
            $criticalLogs = DB::table('pond_telemetries')
                ->join('farms', 'farms.id', '=', 'pond_telemetries.farm_id')
                ->leftJoin('users', 'users.id', '=', 'farms.user_id')
                ->where('pond_telemetries.recorded_at', '>=', $since)
                ->where('pond_telemetries.dissolved_oxygen', '<', 3.5)
                ->select([
                    'pond_telemetries.farm_id',
                    'farms.farm_name',
                    'farms.district',
                    'farms.upazila',
                    'users.name as owner_name',
                    'users.phone as owner_phone',
                    'pond_telemetries.pond_name',
                    'pond_telemetries.dissolved_oxygen',
                    'pond_telemetries.recorded_at',
                ])
                ->orderBy('pond_telemetries.recorded_at', 'desc')
                ->limit(200)
                ->get()
                ->groupBy(function ($item) {
                    $d = trim($item->district ?? 'Other / Unassigned') ?: 'Other / Unassigned';
                    $u = trim($item->upazila ?? 'General') ?: 'General';
                    return "{$d}::{$u}";
                });

            // 4. Global KPIs
            $totalFarmsMonitored = (int) DB::table('pond_telemetries')
                ->join('farms', 'farms.id', '=', 'pond_telemetries.farm_id')
                ->where('pond_telemetries.recorded_at', '>=', $since)
                ->distinct('pond_telemetries.farm_id')
                ->count('pond_telemetries.farm_id');

            $totalReadings = (int) $districtRows->sum('total_readings');
            $totalHypoxiaCount = (int) $districtRows->sum('hypoxia_count');

            $districtHeatmap = [];

            foreach ($districtRows as $row) {
                $districtName = (string) $row->district_name;
                $hypoxiaCount = (int) $row->hypoxia_count;
                $abnormalPhCount = (int) $row->abnormal_ph_count;
                $toxicAmmoniaCount = (int) $row->toxic_ammonia_count;

                $avgDo = $row->avg_do !== null ? round((float) $row->avg_do, 2) : null;
                $minDo = $row->min_do !== null ? round((float) $row->min_do, 2) : null;
                $avgPh = $row->avg_ph !== null ? round((float) $row->avg_ph, 2) : null;
                $avgSalinity = $row->avg_salinity !== null ? round((float) $row->avg_salinity, 1) : null;
                $avgTemp = $row->avg_temperature !== null ? round((float) $row->avg_temperature, 1) : null;

                // Determine district risk level
                $riskLevel = 'optimal';
                if ($hypoxiaCount > 0 || ($avgDo !== null && $avgDo < 4.0)) {
                    $riskLevel = 'critical';
                } elseif (($avgDo !== null && $avgDo < 5.0) || $abnormalPhCount > 0) {
                    $riskLevel = 'warning';
                }

                // Process upazilas for this district
                $upazilaList = [];
                $districtUpazilas = $upazilaRows->get($districtName, collect());

                foreach ($districtUpazilas as $uRow) {
                    $uName = (string) $uRow->upazila_name;
                    $uHypoxia = (int) $uRow->hypoxia_count;
                    $uAvgDo = $uRow->avg_do !== null ? round((float) $uRow->avg_do, 2) : null;

                    $uRisk = 'optimal';
                    if ($uHypoxia > 0 || ($uAvgDo !== null && $uAvgDo < 4.0)) {
                        $uRisk = 'critical';
                    } elseif ($uAvgDo !== null && $uAvgDo < 5.0) {
                        $uRisk = 'warning';
                    }

                    // Top 3 critical farms for this upazila
                    $criticalKey = "{$districtName}::{$uName}";
                    $recentCriticalAlerts = $criticalLogs->get($criticalKey, collect())
                        ->take(3)
                        ->map(fn ($c) => [
                            'farm_id' => $c->farm_id,
                            'farm_name' => $c->farm_name,
                            'owner_name' => $c->owner_name,
                            'owner_phone' => $c->owner_phone,
                            'pond_name' => $c->pond_name,
                            'dissolved_oxygen' => $c->dissolved_oxygen,
                            'recorded_at' => Carbon::parse($c->recorded_at)->toIso8601String(),
                        ])
                        ->values();

                    $upazilaList[] = [
                        'upazila_name' => $uName,
                        'farms_count' => (int) $uRow->farms_count,
                        'total_readings' => (int) $uRow->total_readings,
                        'avg_do' => $uAvgDo,
                        'hypoxia_count' => $uHypoxia,
                        'risk_level' => $uRisk,
                        'critical_farms' => $recentCriticalAlerts,
                    ];
                }

                // Sort upazilas: critical first
                usort($upazilaList, function ($a, $b) {
                    $order = ['critical' => 1, 'warning' => 2, 'optimal' => 3];
                    return ($order[$a['risk_level']] ?? 4) <=> ($order[$b['risk_level']] ?? 4);
                });

                $districtHeatmap[] = [
                    'district_name' => $districtName,
                    'farms_count' => (int) $row->farms_count,
                    'total_readings' => (int) $row->total_readings,
                    'avg_do' => $avgDo,
                    'min_do' => $minDo,
                    'avg_ph' => $avgPh,
                    'avg_salinity' => $avgSalinity,
                    'avg_temperature' => $avgTemp,
                    'hypoxia_count' => $hypoxiaCount,
                    'abnormal_ph_count' => $abnormalPhCount,
                    'toxic_ammonia_count' => $toxicAmmoniaCount,
                    'risk_level' => $riskLevel,
                    'upazilas' => $upazilaList,
                ];
            }

            // Sort districts: critical first, then by total farms desc
            usort($districtHeatmap, function ($a, $b) {
                $order = ['critical' => 1, 'warning' => 2, 'optimal' => 3];
                $cmp = ($order[$a['risk_level']] ?? 4) <=> ($order[$b['risk_level']] ?? 4);
                if ($cmp === 0) {
                    return $b['farms_count'] <=> $a['farms_count'];
                }
                return $cmp;
            });

            $criticalDistrictsCount = count(array_filter($districtHeatmap, fn ($d) => $d['risk_level'] === 'critical'));
            $optimalRate = $totalReadings > 0
                ? round((($totalReadings - $totalHypoxiaCount) / $totalReadings) * 100, 1)
                : 100.0;

            return [
                'time_window_days' => $days,
                'summary_kpis' => [
                    'monitored_districts_count' => count($districtHeatmap),
                    'total_farms_monitored' => $totalFarmsMonitored,
                    'total_readings_window' => $totalReadings,
                    'total_hypoxia_alerts' => $totalHypoxiaCount,
                    'critical_districts_count' => $criticalDistrictsCount,
                    'safe_water_compliance_percent' => $optimalRate,
                ],
                'districts' => $districtHeatmap,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'District water quality heatmap retrieved successfully',
            'data' => $data,
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
