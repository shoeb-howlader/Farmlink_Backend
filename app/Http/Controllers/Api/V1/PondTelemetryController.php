<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BatchSyncPondTelemetryRequest;
use App\Http\Requests\Api\V1\StorePondTelemetryRequest;
use App\Http\Resources\V1\PondTelemetryResource;
use App\Models\Farm;
use App\Models\PondTelemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PondTelemetryController extends ApiController
{
    /**
     * Ensure the authenticated user has permission to access this farm.
     */
    protected function authorizeFarmAccess(Request $request, Farm $farm): void
    {
        $user = $request->user();

        if ($user->hasAnyRole(['admin', 'data_entry_operator'])) {
            return;
        }

        if ($user->hasRole('farmer') && $farm->user_id === $user->id) {
            return;
        }

        abort(403, 'You are not authorized to view or log telemetry for this farm.');
    }

    /**
     * Display a listing of pond water telemetries for a farm.
     */
    public function index(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarmAccess($request, $farm);

        $query = $farm->telemetries()->with('user')->orderBy('recorded_at', 'desc');

        if ($request->filled('pond_name')) {
            $query->where('pond_name', $request->query('pond_name'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('recorded_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('recorded_at', '<=', $request->query('to_date'));
        }

        $perPage = max(1, min((int) ($request->query('per_page') ?? 15), 50));
        $telemetries = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Pond telemetries retrieved successfully',
            'data' => PondTelemetryResource::collection($telemetries->items()),
            'meta' => [
                'current_page' => $telemetries->currentPage(),
                'last_page' => $telemetries->lastPage(),
                'per_page' => $telemetries->perPage(),
                'total' => $telemetries->total(),
            ],
        ]);
    }

    /**
     * Store a newly recorded pond telemetry entry.
     */
    public function store(StorePondTelemetryRequest $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarmAccess($request, $farm);

        $validated = $request->validated();
        $validated['farm_id'] = $farm->id;
        $validated['user_id'] = $request->user()->id;

        if (empty($validated['recorded_at'])) {
            $validated['recorded_at'] = now();
        }

        if (! empty($validated['client_uuid'])) {
            $telemetry = PondTelemetry::updateOrCreate(
                [
                    'farm_id' => $farm->id,
                    'client_uuid' => $validated['client_uuid'],
                ],
                $validated
            );
        } else {
            $telemetry = PondTelemetry::create($validated);
        }

        $telemetry->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Pond telemetry recorded successfully',
            'data' => new PondTelemetryResource($telemetry),
        ], 201);
    }

    /**
     * Batch sync offline telemetry logs collected while disconnected.
     */
    public function batchSync(BatchSyncPondTelemetryRequest $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarmAccess($request, $farm);

        $user = $request->user();
        $items = $request->validated('items');
        $syncedRecords = [];

        DB::transaction(function () use ($farm, $user, $items, &$syncedRecords) {
            foreach ($items as $item) {
                $payload = array_merge($item, [
                    'farm_id' => $farm->id,
                    'user_id' => $user->id,
                    'sync_source' => 'offline_sync',
                ]);

                $record = PondTelemetry::updateOrCreate(
                    [
                        'farm_id' => $farm->id,
                        'client_uuid' => $item['client_uuid'],
                    ],
                    $payload
                );

                $syncedRecords[] = $record;
            }
        });

        return response()->json([
            'success' => true,
            'message' => sprintf('Successfully synced %d offline pond telemetry logs', count($syncedRecords)),
            'data' => [
                'synced_count' => count($syncedRecords),
                'items' => PondTelemetryResource::collection(collect($syncedRecords)),
            ],
        ], 200);
    }

    /**
     * Get 7-day averages, latest telemetry, and advisory alerts for a farm.
     */
    public function summary(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeFarmAccess($request, $farm);

        $latest = $farm->telemetries()->with('user')->orderBy('recorded_at', 'desc')->first();

        $sevenDaysAgo = now()->subDays(7);
        $recentStats = $farm->telemetries()
            ->where('recorded_at', '>=', $sevenDaysAgo)
            ->selectRaw('
                COUNT(*) as total_logs,
                AVG(dissolved_oxygen) as avg_do,
                MIN(dissolved_oxygen) as min_do,
                MAX(dissolved_oxygen) as max_do,
                AVG(ph) as avg_ph,
                MIN(ph) as min_ph,
                MAX(ph) as max_ph,
                AVG(salinity) as avg_salinity,
                AVG(temperature) as avg_temp
            ')
            ->first();

        $distinctPonds = $farm->telemetries()
            ->whereNotNull('pond_name')
            ->distinct()
            ->pluck('pond_name');

        return response()->json([
            'success' => true,
            'message' => 'Telemetry summary retrieved successfully',
            'data' => [
                'latest' => $latest ? new PondTelemetryResource($latest) : null,
                'ponds' => $distinctPonds,
                'seven_day_stats' => [
                    'total_logs' => (int) ($recentStats?->total_logs ?? 0),
                    'avg_do' => $recentStats?->avg_do ? round((float) $recentStats->avg_do, 2) : null,
                    'min_do' => $recentStats?->min_do ? round((float) $recentStats->min_do, 2) : null,
                    'max_do' => $recentStats?->max_do ? round((float) $recentStats->max_do, 2) : null,
                    'avg_ph' => $recentStats?->avg_ph ? round((float) $recentStats->avg_ph, 2) : null,
                    'min_ph' => $recentStats?->min_ph ? round((float) $recentStats->min_ph, 2) : null,
                    'max_ph' => $recentStats?->max_ph ? round((float) $recentStats->max_ph, 2) : null,
                    'avg_salinity' => $recentStats?->avg_salinity ? round((float) $recentStats->avg_salinity, 1) : null,
                    'avg_temperature' => $recentStats?->avg_temp ? round((float) $recentStats->avg_temp, 1) : null,
                ],
            ],
        ]);
    }

    /**
     * Export pond telemetries for a farm as CSV or unpaginated JSON.
     */
    public function export(Request $request, Farm $farm)
    {
        $this->authorizeFarmAccess($request, $farm);

        $query = $farm->telemetries()->with('user')->orderBy('recorded_at', 'desc');

        if ($request->filled('pond_name')) {
            $query->where('pond_name', $request->query('pond_name'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('recorded_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('recorded_at', '<=', $request->query('to_date'));
        }

        $records = $query->get();

        if ($request->query('format') === 'csv') {
            $csvHeaders = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => sprintf('attachment; filename="pond-telemetry-%s-%s.csv"', $farm->id, now()->format('Y-m-d')),
            ];

            $callback = function () use ($records, $farm) {
                $file = fopen('php://output', 'w');
                // UTF-8 BOM for Microsoft Excel compatibility
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

                fputcsv($file, [
                    'Farm ID',
                    'Farm Name',
                    'Pond Name',
                    'Date & Time (UTC)',
                    'Recorded By',
                    'Dissolved Oxygen (mg/L)',
                    'pH Level',
                    'Salinity (ppt)',
                    'Water Temp (°C)',
                    'Transparency (cm)',
                    'Ammonia (mg/L)',
                    'Alkalinity (mg/L CaCO3)',
                    'Weather Condition',
                    'Health Status',
                    'Sync Source',
                    'Notes',
                ]);

                foreach ($records as $row) {
                    $evaluation = $row->evaluateHealthAlerts();
                    fputcsv($file, [
                        $farm->id,
                        $farm->farm_name,
                        $row->pond_name ?? 'Main Pond',
                        $row->recorded_at?->toIso8601String(),
                        $row->user?->name ?? 'N/A',
                        $row->dissolved_oxygen ?? '',
                        $row->ph ?? '',
                        $row->salinity ?? '',
                        $row->temperature ?? '',
                        $row->transparency ?? '',
                        $row->ammonia ?? '',
                        $row->alkalinity ?? '',
                        $row->weather_condition ?? '',
                        $evaluation['status'],
                        $row->sync_source,
                        $row->notes ?? '',
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $csvHeaders);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pond telemetries exported successfully',
            'data' => PondTelemetryResource::collection($records),
            'meta' => [
                'total' => $records->count(),
                'farm_name' => $farm->farm_name,
                'exported_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
