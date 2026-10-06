<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\FarmResource;
use App\Models\Farm;
use App\Models\FarmImage;
use App\Models\District;
use App\Models\Upazila;
use App\Models\Union;
use App\Models\Pourashava;
use App\Services\ImageUploadService;
use App\Traits\HandlesFarmGalleryImages;
use App\Traits\SyncsLocationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FarmController extends ApiController
{
    use SyncsLocationData, HandlesFarmGalleryImages;
    /**
     * Display a listing of all farms with farmer information (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Farm::class);

        $query = Farm::with([
            'user.roles',
            'division',
            'districtModel',
            'upazilaModel',
            'unionModel',
            'pourashava',
            'images',
        ])->withCount(['orders', 'vetRecords', 'consultantRecords']);

        // Filter by district
        if ($request->filled('district') && $request->query('district') !== 'all') {
            $query->where('district', $request->query('district'));
        }

        // Filter by main culture type
        if ($request->filled('culture_type') && $request->query('culture_type') !== 'all') {
            $query->where('main_culture_type', $request->query('culture_type'));
        }

        // Filter by farming system
        if ($request->filled('farming_system') && $request->query('farming_system') !== 'all') {
            $query->where('farming_system', $request->query('farming_system'));
        }

        // Filter by ownership type / farm type
        $ownership = $request->query('ownership_type') ?? $request->query('farm_type');
        if (! empty($ownership) && $ownership !== 'all') {
            $query->where('farm_type', $ownership);
        }

        // Filter by location reconciliation status
        if ($request->boolean('unmatched_locations') || $request->query('location_status') === 'unmatched') {
            $query->where(function ($q) {
                $q->whereNull('district_id')
                    ->orWhereNull('upazila_id')
                    ->orWhere(function ($uq) {
                        $uq->whereNotNull('union')
                            ->where('union', '!=', '')
                            ->whereNull('union_id')
                            ->whereNull('pourashava_id');
                    });
            });
        }

        // Search across farm name, district, upazila, or farmer name/phone
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('farm_name', 'like', "%{$search}%")
                    ->orWhere('district', 'like', "%{$search}%")
                    ->orWhere('upazila', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $sortBy = $request->query('sort_by', 'created_at');
        $rawDir = $request->query('sort_direction') ?? $request->query('sort_dir') ?? 'desc';
        $sortDir = strtolower($rawDir) === 'asc' ? 'asc' : 'desc';

        switch ($sortBy) {
            case 'orders_count':
                $query->orderBy('orders_count', $sortDir)->orderBy('id', 'desc');
                break;
            case 'visits_count':
                $query->orderByRaw('(coalesce(vet_records_count, 0) + coalesce(consultant_records_count, 0)) ' . $sortDir)->orderBy('id', 'desc');
                break;
            case 'total_area':
                $query->orderBy('total_area', $sortDir)->orderBy('id', 'desc');
                break;
            case 'pond_count':
                $query->orderBy('pond_count', $sortDir)->orderBy('id', 'desc');
                break;
            case 'farm_name':
                $query->orderBy('farm_name', $sortDir)->orderBy('id', 'desc');
                break;
            case 'district':
                $query->orderBy('district', $sortDir)->orderBy('id', 'desc');
                break;
            case 'id':
                $query->orderBy('id', $sortDir);
                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $sortDir)->orderBy('id', $sortDir);
                break;
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->paginate($perPage);

        $stats = Farm::selectRaw("
            count(*) as total_farms,
            coalesce(sum(total_area), 0) as total_area,
            coalesce(sum(pond_count), 0) as total_ponds,
            count(case when main_culture_type like '%Shrimp%' or main_culture_type like '%Prawn%' then 1 end) as shrimp_count,
            count(case when district_id is null or upazila_id is null or (\"union\" is not null and \"union\" != '' and union_id is null and pourashava_id is null) then 1 end) as unmatched_count
        ")->first();

        $totalFarms = (int) ($stats->total_farms ?? 0);
        $totalArea = round((float) ($stats->total_area ?? 0), 1);
        $totalPonds = (int) ($stats->total_ponds ?? 0);
        $shrimpCount = (int) ($stats->shrimp_count ?? 0);
        $unmatchedCount = (int) ($stats->unmatched_count ?? 0);

        return response()->json([
            'success' => true,
            'message' => 'Admin farms retrieved successfully',
            'data' => FarmResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'summary' => [
                'total_farms' => $totalFarms,
                'total_area' => $totalArea,
                'total_ponds' => $totalPonds,
                'shrimp_farms' => $shrimpCount,
                'unmatched_locations' => $unmatchedCount,
            ],
        ]);
    }

    /**
     * Display the specified farm with farmer details (Admin only).
     */
    public function show(Request $request, Farm $farm): JsonResponse
    {
        Gate::authorize('viewAdmin', Farm::class);

        return $this->successResponse(
            new FarmResource($farm->load('user')),
            'Farm details retrieved successfully'
        );
    }

    /**
     * Upload an image for a farm (Admin).
     */
    public function uploadImage(\App\Http\Requests\Api\V1\UploadImageRequest $request, Farm $farm, \App\Services\ImageUploadService $uploader): JsonResponse
    {
        Gate::authorize('viewAdmin', Farm::class);

        $file = $request->getImageFile();
        $result = $uploader->storeImageWithThumbnail($file, 'farms');

        $farm->update([
            'image_path' => $result['path'],
            'image_thumbnail_path' => $result['thumbnail_path'],
            'image_url' => $result['url'],
        ]);

        \App\Models\ActivityLog::log(
            'update_farm_image',
            $farm,
            ['name' => $farm->farm_name, 'path' => $result['path']]
        );

        return $this->successResponse([
            'path' => $result['path'],
            'thumbnail_path' => $result['thumbnail_path'],
            'image_url' => $result['url'],
            'image_thumbnail_url' => $result['thumbnail_url'],
            'farm' => new FarmResource($farm->fresh('user')),
        ], 'Farm image uploaded successfully');
    }

    /**
     * Store a newly created farm linked to a farmer (Admin and DEO).
     */
    public function store(\App\Http\Requests\Api\V1\StoreFarmRequest $request): JsonResponse
    {
        Gate::authorize('create', Farm::class);

        $userId = $request->input('user_id');
        if (! $userId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user_id' => ['The user_id field is required when registering a farm as staff.'],
            ]);
        }

        $data = $this->syncLocationData($request->validated());

        $farm = Farm::create(array_merge(
            $data,
            ['user_id' => $userId]
        ));

        \App\Models\ActivityLog::log(
            'farm.created',
            $farm,
            ['farm_name' => $farm->farm_name, 'farmer_id' => $userId, 'district' => $farm->district],
            $request->user()
        );

        return $this->createdResponse(
            new FarmResource($farm->load('user')),
            'Farm registered successfully'
        );
    }

    /**
     * Update an existing farm (Admin and DEO).
     */
    public function update(\App\Http\Requests\Api\V1\UpdateFarmRequest $request, Farm $farm): JsonResponse
    {
        Gate::authorize('update', $farm);

        $data = $this->syncLocationData($request->validated());

        if ($request->has('user_id') && $request->filled('user_id')) {
            $data['user_id'] = $request->input('user_id');
        }

        $farm->update($data);

        \App\Models\ActivityLog::log(
            'farm.updated',
            $farm,
            ['farm_name' => $farm->farm_name, 'district' => $farm->district],
            $request->user()
        );

        return $this->successResponse(
            new FarmResource($farm->fresh(['user', 'division', 'districtModel', 'upazilaModel', 'unionModel', 'pourashava'])),
            'Farm updated successfully'
        );
    }

    /**
     * Get report and list of farms requiring location reconciliation.
     */
    public function unmatchedLocations(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Farm::class);

        $totalFarms = Farm::count();
        $unmatchedQuery = Farm::with(['user', 'division', 'districtModel', 'upazilaModel', 'unionModel', 'pourashava'])
            ->where(function ($q) {
                $q->whereNull('district_id')
                    ->orWhereNull('upazila_id')
                    ->orWhere(function ($uq) {
                        $uq->whereNotNull('union')
                            ->where('union', '!=', '')
                            ->whereNull('union_id')
                            ->whereNull('pourashava_id');
                    });
            });

        $totalUnmatched = (clone $unmatchedQuery)->count();
        $fullyMatched = $totalFarms - $totalUnmatched;

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $unmatchedQuery->where(function ($q) use ($search) {
                $q->where('farm_name', 'like', "%{$search}%")
                    ->orWhere('district', 'like', "%{$search}%")
                    ->orWhere('upazila', 'like', "%{$search}%")
                    ->orWhere('union', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $unmatchedQuery->orderBy('id', 'desc')->paginate($perPage);

        // Attach diagnostic hints for each farm
        $items = collect($paginated->items())->map(function ($farm) {
            $issues = [];
            if (!$farm->district_id) {
                $issues[] = "District '{$farm->district}' not matched in canonical table.";
            } elseif (!$farm->upazila_id) {
                $issues[] = "Upazila '{$farm->upazila}' not found under District '{$farm->district}'.";
            } elseif (!empty($farm->union) && !$farm->union_id && !$farm->pourashava_id) {
                $issues[] = "Union/Pourashava '{$farm->union}' not found under Upazila '{$farm->upazila}'.";
            }

            $res = (new FarmResource($farm))->toArray(request());
            $res['reconciliation_issues'] = $issues;
            return $res;
        });

        return response()->json([
            'success' => true,
            'summary' => [
                'total_farms' => $totalFarms,
                'fully_matched' => $fullyMatched,
                'needs_reconciliation' => $totalUnmatched,
            ],
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Update location foreign keys for a farm (Admin reconciliation).
     */
    public function updateLocation(Request $request, Farm $farm): JsonResponse
    {
        Gate::authorize('viewAdmin', Farm::class);

        $validated = $request->validate([
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'upazila_id' => ['required', 'integer', 'exists:upazilas,id'],
            'union_id' => ['nullable', 'integer', 'exists:unions,id'],
            'pourashava_id' => ['nullable', 'integer', 'exists:pourashavas,id'],
            'village' => ['nullable', 'string', 'max:100'],
            'farm_address' => ['nullable', 'string'],
        ]);

        $data = $this->syncLocationData($validated);

        // Explicitly clear mutual exclusivity if union or pourashava selected
        if (!empty($validated['union_id'])) {
            $data['pourashava_id'] = null;
        } elseif (!empty($validated['pourashava_id'])) {
            $data['union_id'] = null;
        }

        $farm->update($data);

        \App\Models\ActivityLog::log(
            'farm.location_reconciled',
            $farm,
            [
                'district_id' => $farm->district_id,
                'upazila_id' => $farm->upazila_id,
                'union_id' => $farm->union_id,
                'pourashava_id' => $farm->pourashava_id,
            ],
            $request->user()
        );

        return $this->successResponse(
            new FarmResource($farm->fresh(['user', 'division', 'districtModel', 'upazilaModel', 'unionModel', 'pourashava'])),
            'Farm location reconciled and updated successfully'
        );
    }
}

