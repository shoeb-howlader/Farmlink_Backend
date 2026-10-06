<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreFarmRequest;
use App\Http\Requests\Api\V1\UpdateFarmRequest;
use App\Http\Resources\V1\FarmResource;
use App\Models\Farm;
use App\Traits\HandlesFarmGalleryImages;
use App\Traits\SyncsLocationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FarmController extends ApiController
{
    use SyncsLocationData, HandlesFarmGalleryImages;
    /**
     * Display a listing of the farms for the authenticated user (or filtered for staff).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Farm::class);

        $user = $request->user();

        $query = Farm::with([
            'division',
            'districtModel',
            'upazilaModel',
            'unionModel',
            'pourashava',
            'images',
        ])->withCount(['orders', 'vetRecords', 'consultantRecords']);

        if (! $user->hasRole('admin')) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        } else {
            $query->where('user_id', $user->id);
        }

        $perPage = max(1, min((int) ($request->query('per_page') ?? 15), 50));
        $farms = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Farms retrieved successfully',
            'data' => FarmResource::collection($farms->items()),
            'meta' => [
                'current_page' => $farms->currentPage(),
                'last_page' => $farms->lastPage(),
                'per_page' => $farms->perPage(),
                'total' => $farms->total(),
            ],
        ]);
    }

    /**
     * Store a newly created farm linked to the authenticated user.
     */
    public function store(StoreFarmRequest $request): JsonResponse
    {
        Gate::authorize('create', Farm::class);

        $userId = ($request->user()->hasRole('admin') && $request->filled('user_id'))
            ? $request->input('user_id')
            : $request->user()->id;

        $data = $this->syncLocationData($request->validated());

        $farm = Farm::create(array_merge(
            $data,
            ['user_id' => $userId]
        ));

        return $this->createdResponse(
            new FarmResource($farm),
            'Farm created successfully'
        );
    }

    /**
     * Display the specified farm.
     */
    public function show(Farm $farm): JsonResponse
    {
        Gate::authorize('view', $farm);

        return $this->successResponse(
            new FarmResource($farm),
            'Farm retrieved successfully'
        );
    }

    /**
     * Update the specified farm.
     */
    public function update(UpdateFarmRequest $request, Farm $farm): JsonResponse
    {
        Gate::authorize('update', $farm);

        $data = $this->syncLocationData($request->validated());
        $farm->update($data);

        return $this->successResponse(
            new FarmResource($farm),
            'Farm updated successfully'
        );
    }

    /**
     * Remove the specified farm.
     */
    public function destroy(Farm $farm): JsonResponse
    {
        Gate::authorize('delete', $farm);

        $farm->delete();

        return $this->successResponse(
            null,
            'Farm deleted successfully'
        );
    }

    /**
     * Upload an image for the farm.
     */
    public function uploadImage(\App\Http\Requests\Api\V1\UploadImageRequest $request, Farm $farm, \App\Services\ImageUploadService $imageUploadService): JsonResponse
    {
        Gate::authorize('update', $farm);

        $file = $request->getImageFile();
        $paths = $imageUploadService->uploadAndThumbnail($file, 'farms');

        $farm->update([
            'image_path' => $paths['path'],
            'image_thumbnail_path' => $paths['thumbnail_path'],
        ]);

        return $this->successResponse(
            new FarmResource($farm),
            'Farm image uploaded successfully'
        );
    }
}
