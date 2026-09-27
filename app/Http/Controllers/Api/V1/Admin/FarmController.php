<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\FarmResource;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Facades\Gate;

class FarmController extends ApiController
{
    /**
     * Display a listing of all farms with farmer information (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Farm::class);

        $query = Farm::with('user')
            ->withCount(['orders', 'vetRecords', 'consultantRecords']);

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
        $sortDir = strtolower($request->query('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        switch ($sortBy) {
            case 'orders_count':
                $query->orderBy('orders_count', $sortDir);
                break;
            case 'visits_count':
                $query->orderByRaw('(coalesce(vet_records_count, 0) + coalesce(consultant_records_count, 0)) ' . $sortDir);
                break;
            case 'total_area':
                $query->orderBy('total_area', $sortDir);
                break;
            case 'pond_count':
                $query->orderBy('pond_count', $sortDir);
                break;
            case 'farm_name':
                $query->orderBy('farm_name', $sortDir);
                break;
            default:
                $query->latest();
                break;
        }

        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $paginated = $query->paginate($perPage);

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

        $farm = Farm::create(array_merge(
            $request->validated(),
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
}
