<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    /**
     * Store an uploaded image, auto-compress to WebP (using GD), and generate a square thumbnail.
     *
     * @param UploadedFile $file
     * @param string $folder Relative directory under storage/app/public (e.g. 'avatars', 'products', 'products/editor')
     * @param int $thumbnailSize
     * @return array{path: string, thumbnail_path: string, url: string, thumbnail_url: string}
     */
    public function storeImageWithThumbnail(UploadedFile $file, string $folder, int $thumbnailSize = 150): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
            $extension = 'jpg';
        }

        $uuid = Str::uuid()->toString();
        $webpSaved = false;
        $originalFilename = "{$uuid}.{$extension}";
        $originalPath = "{$folder}/{$originalFilename}";

        // Attempt WebP auto-compression
        if (function_exists('imagewebp')) {
            $webpData = $this->compressToWebP($file->getRealPath());
            if ($webpData !== null) {
                $originalFilename = "{$uuid}.webp";
                $originalPath = "{$folder}/{$originalFilename}";
                Storage::disk('public')->put($originalPath, $webpData);
                $webpSaved = true;
            }
        }

        // Fallback: store original file
        if (! $webpSaved) {
            Storage::disk('public')->putFileAs($folder, $file, $originalFilename);
        }

        // Generate thumbnail
        $thumbExt = (function_exists('imagewebp') || $webpSaved) ? 'webp' : $extension;
        $thumbnailFilename = "{$uuid}.{$thumbExt}";
        $thumbnailPath = "{$folder}/thumbnails/{$thumbnailFilename}";

        $this->generateThumbnail($file->getRealPath(), $thumbnailPath, $thumbnailSize, $thumbExt);

        return [
            'path' => $originalPath,
            'thumbnail_path' => $thumbnailPath,
            'url' => Storage::disk('public')->url($originalPath),
            'thumbnail_url' => Storage::disk('public')->url($thumbnailPath),
        ];
    }

    /**
     * Convert and compress an image file to WebP binary data using GD.
     */
    protected function compressToWebP(string $sourcePath, int $quality = 85): ?string
    {
        $imageInfo = @getimagesize($sourcePath);
        if (! $imageInfo) {
            return null;
        }

        [$width, $height, $imageType] = $imageInfo;

        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => null,
        };

        if (! $sourceImage) {
            return null;
        }

        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_WEBP) {
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
        }

        ob_start();
        $success = imagewebp($sourceImage, null, $quality);
        $data = ob_get_clean();

        imagedestroy($sourceImage);

        return $success ? $data : null;
    }

    /**
     * Generate and save a square-cropped resized thumbnail using GD.
     */
    protected function generateThumbnail(string $sourcePath, string $targetRelativePath, int $size, string $extension): void
    {
        $imageInfo = @getimagesize($sourcePath);
        if (! $imageInfo) {
            // Fallback: copy original as thumbnail if GD cannot inspect
            Storage::disk('public')->copy(dirname($targetRelativePath, 2) . '/' . basename($targetRelativePath), $targetRelativePath);
            return;
        }

        [$width, $height, $imageType] = $imageInfo;

        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => null,
        };

        if (! $sourceImage) {
            Storage::disk('public')->copy(dirname($targetRelativePath, 2) . '/' . basename($targetRelativePath), $targetRelativePath);
            return;
        }

        // Center crop calculation for square aspect ratio
        $minDim = min($width, $height);
        $srcX = (int) (($width - $minDim) / 2);
        $srcY = (int) (($height - $minDim) / 2);

        $thumbImage = imagecreatetruecolor($size, $size);

        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_WEBP || $extension === 'webp') {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbImage, 0, 0, $size, $size, $transparent);
        }

        imagecopyresampled(
            $thumbImage,
            $sourceImage,
            0,
            0,
            $srcX,
            $srcY,
            $size,
            $size,
            $minDim,
            $minDim
        );

        // Save thumbnail to public storage
        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($thumbImage, null, 85);
        } else {
            match ($imageType) {
                IMAGETYPE_PNG => imagepng($thumbImage, null, 8),
                default => imagejpeg($thumbImage, null, 85),
            };
        }
        $thumbData = ob_get_clean();

        Storage::disk('public')->put($targetRelativePath, $thumbData);

        imagedestroy($sourceImage);
        imagedestroy($thumbImage);
    }
}
