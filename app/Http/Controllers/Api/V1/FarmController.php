<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreFarmRequest;
use App\Http\Requests\Api\V1\UpdateFarmRequest;
use App\Http\Resources\V1\FarmResource;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FarmController extends ApiController
{
    /**
     * Display a listing of the farms for the authenticated user (or filtered for staff).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Farm::class);

        $user = $request->user();

        $query = Farm::query();

        if (! $user->hasRole('admin')) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        } else {
            $query->where('user_id', $user->id);
        }

        $farms = $query->latest()->get();

        return $this->successResponse(
            FarmResource::collection($farms),
            'Farms retrieved successfully'
        );
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

        $farm = Farm::create(array_merge(
            $request->validated(),
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

        $farm->update($request->validated());

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
