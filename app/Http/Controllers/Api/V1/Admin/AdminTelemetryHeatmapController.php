<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Farm;
use App\Models\PondTelemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminTelemetryHeatmapController extends ApiController
{
    /**
     * Display aggregated district and upazila water quality heatmap data
     * for Fisheries Department and Administrative oversight.
     */
    public function index(Request $request): JsonResponse
    {
        $days = max(1, min((int) ($request->query('days') ?? 14), 90));
        $since = Carbon::now()->subDays($days);

        // Fetch recent telemetries with farm details
        $telemetries = PondTelemetry::with(['farm.user', 'user'])
            ->where('recorded_at', '>=', $since)
            ->whereHas('farm')
            ->orderBy('recorded_at', 'desc')
            ->get();

        // Group by district (normalize fallback)
        $districtGroups = $telemetries->groupBy(function ($t) {
            $district = $t->farm->district ?? 'Other / Unassigned';
            return trim($district) ?: 'Other / Unassigned';
        });

        $districtHeatmap = [];
        $totalHypoxiaCount = 0;
        $totalReadings = $telemetries->count();
        $totalFarmsSet = [];

        foreach ($districtGroups as $districtName => $districtLogs) {
            $farmIdsInDistrict = $districtLogs->pluck('farm_id')->unique()->all();
            foreach ($farmIdsInDistrict as $fId) {
                $totalFarmsSet[$fId] = true;
            }

            $hypoxiaCount = $districtLogs->filter(fn ($t) => !is_null($t->dissolved_oxygen) && (float) $t->dissolved_oxygen < 3.5)->count();
            $abnormalPhCount = $districtLogs->filter(fn ($t) => !is_null($t->ph) && ((float) $t->ph < 7.0 || (float) $t->ph > 9.0))->count();
            $toxicAmmoniaCount = $districtLogs->filter(fn ($t) => !is_null($t->ammonia) && (float) $t->ammonia > 0.1)->count();

            $totalHypoxiaCount += $hypoxiaCount;

            $validDo = $districtLogs->whereNotNull('dissolved_oxygen')->pluck('dissolved_oxygen');
            $validPh = $districtLogs->whereNotNull('ph')->pluck('ph');
            $validSalinity = $districtLogs->whereNotNull('salinity')->pluck('salinity');
            $validTemp = $districtLogs->whereNotNull('temperature')->pluck('temperature');

            $avgDo = $validDo->count() > 0 ? round($validDo->avg(), 2) : null;
            $minDo = $validDo->count() > 0 ? round($validDo->min(), 2) : null;
            $avgPh = $validPh->count() > 0 ? round($validPh->avg(), 2) : null;
            $avgSalinity = $validSalinity->count() > 0 ? round($validSalinity->avg(), 1) : null;
            $avgTemp = $validTemp->count() > 0 ? round($validTemp->avg(), 1) : null;

            // Health status for the district
            $riskLevel = 'optimal';
            if ($hypoxiaCount > 0 || ($avgDo !== null && $avgDo < 4.0)) {
                $riskLevel = 'critical';
            } elseif (($avgDo !== null && $avgDo < 5.0) || $abnormalPhCount > 0) {
                $riskLevel = 'warning';
            }

            // Upazila breakdown
            $upazilaGroups = $districtLogs->groupBy(function ($t) {
                return trim($t->farm->upazila ?? 'General') ?: 'General';
            });

            $upazilaList = [];
            foreach ($upazilaGroups as $upazilaName => $upazilaLogs) {
                $uDo = $upazilaLogs->whereNotNull('dissolved_oxygen')->pluck('dissolved_oxygen');
                $uHypoxia = $upazilaLogs->filter(fn ($t) => !is_null($t->dissolved_oxygen) && (float) $t->dissolved_oxygen < 3.5)->count();
                $uAvgDo = $uDo->count() > 0 ? round($uDo->avg(), 2) : null;

                $uRisk = 'optimal';
                if ($uHypoxia > 0 || ($uAvgDo !== null && $uAvgDo < 4.0)) {
                    $uRisk = 'critical';
                } elseif ($uAvgDo !== null && $uAvgDo < 5.0) {
                    $uRisk = 'warning';
                }

                // Sample recent critical farms in this upazila
                $recentCriticalAlerts = $upazilaLogs
                    ->filter(fn ($t) => !is_null($t->dissolved_oxygen) && (float) $t->dissolved_oxygen < 3.5)
                    ->take(3)
                    ->map(fn ($t) => [
                        'farm_id' => $t->farm_id,
                        'farm_name' => $t->farm->farm_name,
                        'owner_name' => $t->farm->user?->name,
                        'owner_phone' => $t->farm->user?->phone,
                        'pond_name' => $t->pond_name,
                        'dissolved_oxygen' => $t->dissolved_oxygen,
                        'recorded_at' => $t->recorded_at?->toIso8601String(),
                    ])
                    ->values();

                $upazilaList[] = [
                    'upazila_name' => $upazilaName,
                    'farms_count' => $upazilaLogs->pluck('farm_id')->unique()->count(),
                    'total_readings' => $upazilaLogs->count(),
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
                'farms_count' => count($farmIdsInDistrict),
                'total_readings' => $districtLogs->count(),
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

        // Sort districts: critical first, then total farms desc
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

        return response()->json([
            'success' => true,
            'message' => 'District water quality heatmap retrieved successfully',
            'data' => [
                'time_window_days' => $days,
                'summary_kpis' => [
                    'monitored_districts_count' => count($districtHeatmap),
                    'total_farms_monitored' => count($totalFarmsSet),
                    'total_readings_window' => $totalReadings,
                    'total_hypoxia_alerts' => $totalHypoxiaCount,
                    'critical_districts_count' => $criticalDistrictsCount,
                    'safe_water_compliance_percent' => $optimalRate,
                ],
                'districts' => $districtHeatmap,
            ],
        ]);
    }
}
