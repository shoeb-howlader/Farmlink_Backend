<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadImageRequest;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;

class VisitPhotoController extends ApiController
{
    /**
     * Upload an image for a visit record (preview before saving).
     */
    public function upload(UploadImageRequest $request, ImageUploadService $uploader): JsonResponse
    {
        $file = $request->getImageFile();
        $result = $uploader->storeImageWithThumbnail($file, 'visit_photos');

        return $this->successResponse([
            'photo_path' => $result['path'],
            'url' => $result['url'],
            'thumbnail_url' => $result['thumbnail_url'],
        ], 'Visit photo uploaded successfully');
    }
}
