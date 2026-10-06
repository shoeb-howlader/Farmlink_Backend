<?php

namespace App\Traits;

use App\Http\Resources\V1\FarmResource;
use App\Models\Farm;
use App\Models\FarmImage;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

trait HandlesFarmGalleryImages
{
    /**
     * Upload one or multiple gallery photos for a farm.
     */
    public function uploadGalleryImages(Request $request, Farm $farm, ImageUploadService $uploader): JsonResponse
    {
        Gate::authorize('update', $farm);

        $request->validate([
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'image' => ['sometimes', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'caption' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $files = [];
        if ($request->hasFile('images')) {
            $files = $request->file('images');
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        }

        if (empty($files)) {
            return $this->errorResponse('No valid images were provided.', 422);
        }

        $caption = $request->input('caption');
        $category = $request->input('category', 'general');
        $maxSort = (int) $farm->images()->max('sort_order');
        $hasPrimary = $farm->images()->where('is_primary', true)->exists();

        $createdImages = [];

        foreach ($files as $file) {
            $maxSort++;
            $result = $uploader->storeImageWithThumbnail($file, 'farms');

            $isPrimary = false;
            if (! $hasPrimary) {
                $isPrimary = true;
                $hasPrimary = true;

                $farm->update([
                    'image_path' => $result['path'],
                    'image_thumbnail_path' => $result['thumbnail_path'],
                    'image_url' => $result['url'],
                ]);
            }

            $farmImage = FarmImage::create([
                'farm_id' => $farm->id,
                'image_path' => $result['path'],
                'image_thumbnail_path' => $result['thumbnail_path'],
                'image_url' => $result['url'],
                'caption' => $caption,
                'category' => $category,
                'sort_order' => $maxSort,
                'is_primary' => $isPrimary,
            ]);

            $createdImages[] = [
                'id' => $farmImage->id,
                'farm_id' => $farmImage->farm_id,
                'image_path' => $farmImage->image_path,
                'image_url' => $farmImage->url,
                'image_thumbnail_url' => $farmImage->thumbnail_url,
                'caption' => $farmImage->caption,
                'category' => $farmImage->category,
                'sort_order' => $farmImage->sort_order,
                'is_primary' => $farmImage->is_primary,
                'created_at' => $farmImage->created_at?->toISOString(),
            ];
        }

        if (class_exists(\App\Models\ActivityLog::class)) {
            \App\Models\ActivityLog::log(
                'farm.gallery_images_uploaded',
                $farm,
                ['farm_name' => $farm->farm_name, 'count' => count($createdImages)],
                $request->user()
            );
        }

        return $this->successResponse([
            'uploaded' => $createdImages,
            'farm' => new FarmResource($farm->fresh(['user', 'images'])),
        ], 'Farm photos uploaded successfully');
    }

    /**
     * Delete a gallery photo from a farm.
     */
    public function deleteGalleryImage(Request $request, Farm $farm, FarmImage $image): JsonResponse
    {
        Gate::authorize('update', $farm);

        if ($image->farm_id !== $farm->id) {
            return $this->errorResponse('Image does not belong to this farm.', 404);
        }

        $wasPrimary = $image->is_primary;

        if ($image->image_path) {
            Storage::disk('public')->delete($image->image_path);
        }
        if ($image->image_thumbnail_path) {
            Storage::disk('public')->delete($image->image_thumbnail_path);
        }

        $image->delete();

        if ($wasPrimary) {
            $nextImage = $farm->images()->first();
            if ($nextImage) {
                $nextImage->update(['is_primary' => true]);
                $farm->update([
                    'image_path' => $nextImage->image_path,
                    'image_thumbnail_path' => $nextImage->image_thumbnail_path,
                    'image_url' => $nextImage->image_url,
                ]);
            } else {
                $farm->update([
                    'image_path' => null,
                    'image_thumbnail_path' => null,
                    'image_url' => null,
                ]);
            }
        }

        if (class_exists(\App\Models\ActivityLog::class)) {
            \App\Models\ActivityLog::log(
                'farm.gallery_image_deleted',
                $farm,
                ['farm_name' => $farm->farm_name, 'image_id' => $image->id],
                $request->user()
            );
        }

        return $this->successResponse([
            'farm' => new FarmResource($farm->fresh(['user', 'images'])),
        ], 'Farm photo deleted successfully');
    }

    /**
     * Set a gallery photo as the primary cover image.
     */
    public function setPrimaryImage(Request $request, Farm $farm, FarmImage $image): JsonResponse
    {
        Gate::authorize('update', $farm);

        if ($image->farm_id !== $farm->id) {
            return $this->errorResponse('Image does not belong to this farm.', 404);
        }

        $farm->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        $farm->update([
            'image_path' => $image->image_path,
            'image_thumbnail_path' => $image->image_thumbnail_path,
            'image_url' => $image->image_url,
        ]);

        if (class_exists(\App\Models\ActivityLog::class)) {
            \App\Models\ActivityLog::log(
                'farm.cover_photo_updated',
                $farm,
                ['farm_name' => $farm->farm_name, 'image_id' => $image->id],
                $request->user()
            );
        }

        return $this->successResponse([
            'farm' => new FarmResource($farm->fresh(['user', 'images'])),
        ], 'Cover photo updated successfully');
    }

    /**
     * Update caption, category, or order of a gallery photo.
     */
    public function updateGalleryImage(Request $request, Farm $farm, FarmImage $image): JsonResponse
    {
        Gate::authorize('update', $farm);

        if ($image->farm_id !== $farm->id) {
            return $this->errorResponse('Image does not belong to this farm.', 404);
        }

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $image->update($validated);

        return $this->successResponse([
            'image' => [
                'id' => $image->id,
                'farm_id' => $image->farm_id,
                'image_path' => $image->image_path,
                'image_url' => $image->url,
                'image_thumbnail_url' => $image->thumbnail_url,
                'caption' => $image->caption,
                'category' => $image->category,
                'sort_order' => $image->sort_order,
                'is_primary' => $image->is_primary,
                'created_at' => $image->created_at?->toISOString(),
            ],
            'farm' => new FarmResource($farm->fresh(['user', 'images'])),
        ], 'Photo details updated successfully');
    }
}
