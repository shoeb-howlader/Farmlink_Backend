<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Division;
use App\Models\Pourashava;
use App\Models\Union;
use App\Models\Upazila;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /**
     * Cache TTL in seconds (24 hours).
     */
    protected const CACHE_TTL = 86400;

    /**
     * Get all divisions.
     */
    public function divisions(): JsonResponse
    {
        $divisions = Cache::remember('locations.divisions', self::CACHE_TTL, function () {
            return Division::select('id', 'name', 'bn_name')
                ->orderBy('name')
                ->get()
                ->toArray();
        });

        return response()->json([
            'status' => 'success',
            'data' => $divisions,
        ])->header('Cache-Control', 'public, max-age=86400, stale-while-revalidate=604800');
    }

    /**
     * Get districts, optionally filtered by division_id.
     */
    public function districts(Request $request): JsonResponse
    {
        $divisionId = $request->query('division_id');
        $cacheKey = 'locations.districts.' . ($divisionId ?: 'all');

        $districts = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($divisionId) {
            $query = District::select('id', 'division_id', 'name', 'bn_name');

            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }

            return $query->orderBy('name')->get()->toArray();
        });

        return response()->json([
            'status' => 'success',
            'data' => $districts,
        ])->header('Cache-Control', 'public, max-age=86400, stale-while-revalidate=604800');
    }

    /**
     * Get upazilas, optionally filtered by district_id.
     */
    public function upazilas(Request $request): JsonResponse
    {
        $districtId = $request->query('district_id');
        $cacheKey = 'locations.upazilas.' . ($districtId ?: 'all');

        $upazilas = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($districtId) {
            $query = Upazila::select('id', 'district_id', 'name', 'bn_name');

            if ($districtId) {
                $query->where('district_id', $districtId);
            }

            return $query->orderBy('name')->get()->toArray();
        });

        return response()->json([
            'status' => 'success',
            'data' => $upazilas,
        ])->header('Cache-Control', 'public, max-age=86400, stale-while-revalidate=604800');
    }

    /**
     * Get unions, optionally filtered by upazila_id.
     */
    public function unions(Request $request): JsonResponse
    {
        $upazilaId = $request->query('upazila_id');
        $cacheKey = 'locations.unions.' . ($upazilaId ?: 'all');

        $unions = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($upazilaId) {
            $query = Union::select('id', 'upazila_id', 'name', 'bn_name');

            if ($upazilaId) {
                $query->where('upazila_id', $upazilaId);
            }

            return $query->orderBy('name')->get()->toArray();
        });

        return response()->json([
            'status' => 'success',
            'data' => $unions,
        ])->header('Cache-Control', 'public, max-age=86400, stale-while-revalidate=604800');
    }

    /**
     * Get pourashavas, optionally filtered by upazila_id.
     */
    public function pourashavas(Request $request): JsonResponse
    {
        $upazilaId = $request->query('upazila_id');
        $cacheKey = 'locations.pourashavas.' . ($upazilaId ?: 'all');

        $pourashavas = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($upazilaId) {
            $query = Pourashava::select('id', 'upazila_id', 'name', 'bn_name');

            if ($upazilaId) {
                $query->where('upazila_id', $upazilaId);
            }

            return $query->orderBy('name')->get()->toArray();
        });

        return response()->json([
            'status' => 'success',
            'data' => $pourashavas,
        ])->header('Cache-Control', 'public, max-age=86400, stale-while-revalidate=604800');
    }
}
