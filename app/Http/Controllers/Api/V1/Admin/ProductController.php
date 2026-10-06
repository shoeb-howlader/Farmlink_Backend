<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\ProductDocumentResource;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\ProductStockAdjustment;
use App\Services\RichTextSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends ApiController
{
    /**
     * Display a paginated listing of all products for admin management.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $query = Product::query()
            ->with(['variants', 'images', 'specs', 'documents'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($request->filled('category') && $request->query('category') !== 'all') {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $isActive = $request->query('status') === 'active';
            $query->where('is_active', $isActive);
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Stock status filtering
        if ($request->filled('stock_status') && $request->query('stock_status') !== 'all') {
            $stockStatus = $request->query('stock_status');
            if ($stockStatus === 'out_of_stock') {
                $query->where(function ($q) {
                    $q->where('stock', '<=', 0)
                      ->orWhereDoesntHave('variants', function ($qv) {
                          $qv->where('stock', '>', 0);
                      });
                });
            } elseif ($stockStatus === 'low_stock') {
                $query->where(function ($q) {
                    $q->whereColumn('stock', '<=', 'low_stock_threshold')
                      ->orWhereHas('variants', function ($qv) {
                          $qv->whereColumn('stock', '<=', 'low_stock_threshold');
                      });
                });
            }
        }

        // Sortable columns (name, price, stock, status)
        $sort = $request->query('sort', 'latest');
        $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'name' => $query->orderBy('name', $direction),
            'price' => $query->orderBy('price', $direction),
            'stock' => $query->orderBy('stock', $direction),
            'status', 'is_active' => $query->orderBy('is_active', $direction),
            default => $query->latest(),
        };

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->paginate($perPage);

        $lowStockCount = Product::where('is_active', true)->where(function ($q) {
            $q->whereColumn('stock', '<=', 'low_stock_threshold')
              ->orWhereHas('variants', function ($qv) {
                  $qv->whereColumn('stock', '<=', 'low_stock_threshold');
              });
        })->count();

        $outOfStockCount = Product::where('is_active', true)->where(function ($q) {
            $q->where('stock', '<=', 0)
              ->orWhereDoesntHave('variants', function ($qv) {
                  $qv->where('stock', '>', 0);
              });
        })->count();

        return response()->json([
            'success' => true,
            'message' => 'Admin products retrieved successfully',
            'data' => ProductResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
            ],
        ]);
    }
    /**
     * Display a single product with all relations for admin editing.
     */
    public function show(Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $product->load(['variants', 'images', 'specs', 'documents']);

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Store a newly created product with optional variants and specs.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'usage_instructions' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array', 'min:1'],
            'variants.*.variant_label' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sale_starts_at' => ['nullable', 'date'],
            'variants.*.sale_ends_at' => ['nullable', 'date'],
            'variants.*.stock' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'specs' => ['nullable', 'array'],
            'specs.*.label' => ['required_with:specs', 'string', 'max:100'],
            'specs.*.value' => ['required_with:specs', 'string'],
            'specs.*.sort_order' => ['nullable', 'integer'],
        ]);

        $variantsData = $validated['variants'] ?? [];
        $initialPrice = ! empty($variantsData) ? (float) ($variantsData[0]['price'] ?? 0) : (float) ($validated['price'] ?? 0);
        $initialStock = ! empty($variantsData) ? array_sum(array_column($variantsData, 'stock')) : (int) ($validated['stock'] ?? 0);

        // Sanitize rich text inputs
        $sanitizedDesc = isset($validated['description']) ? RichTextSanitizer::sanitize($validated['description']) : null;
        $sanitizedUsage = isset($validated['usage_instructions']) ? RichTextSanitizer::sanitize($validated['usage_instructions']) : null;
        $shortDesc = $validated['short_description'] ?? ($sanitizedDesc ? Str::limit(strip_tags($sanitizedDesc), 155) : null);

        $product = Product::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'short_description' => $shortDesc,
            'price' => $initialPrice,
            'stock' => $initialStock,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
            'image_url' => $validated['image_url'] ?? null,
            'description' => $sanitizedDesc,
            'usage_instructions' => $sanitizedUsage,
            'video_url' => $validated['video_url'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
        ]);

        // Save specifications
        if (! empty($validated['specs'])) {
            foreach ($validated['specs'] as $idx => $spec) {
                if (! empty($spec['label']) && ! empty($spec['value'])) {
                    $product->specs()->create([
                        'label' => trim($spec['label']),
                        'value' => trim($spec['value']),
                        'sort_order' => $spec['sort_order'] ?? $idx,
                    ]);
                }
            }
        }

        if (! empty($variantsData)) {
            $product->variants()->delete();
            $hasDefault = false;
            foreach ($variantsData as $idx => $v) {
                $isDef = ! empty($v['is_default']);
                if ($isDef && ! $hasDefault) {
                    $hasDefault = true;
                } elseif ($isDef && $hasDefault) {
                    $isDef = false;
                }
                $vSlug = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $v['variant_label'] ?? ''), '-'));
                $product->variants()->create([
                    'variant_label' => $v['variant_label'],
                    'sku' => ! empty($v['sku']) ? $v['sku'] : ('PRD-' . $product->id . '-' . ($vSlug ?: ('VAR' . ($idx + 1)))),
                    'price' => $v['price'],
                    'compare_at_price' => $v['compare_at_price'] ?? null,
                    'sale_starts_at' => $v['sale_starts_at'] ?? null,
                    'sale_ends_at' => $v['sale_ends_at'] ?? null,
                    'stock' => $v['stock'],
                    'low_stock_threshold' => $v['low_stock_threshold'] ?? 10,
                    'is_default' => $isDef,
                ]);
            }
            if (! $hasDefault && $product->variants()->count() > 0) {
                $product->variants()->first()->update(['is_default' => true]);
            }
            $product->syncAggregateStockAndPrice();
        } else {
            // Default single variant
            $product->variants()->updateOrCreate([
                'variant_label' => 'Standard',
            ], [
                'sku' => 'PRD-' . $product->id . '-STANDARD',
                'price' => $product->price,
                'stock' => $product->stock,
                'low_stock_threshold' => $product->low_stock_threshold ?? 10,
                'is_default' => true,
            ]);
            $product->syncAggregateStockAndPrice();
        }

        \App\Models\ActivityLog::log(
            'create_product',
            $product,
            ['name' => $product->name, 'price' => $product->price, 'stock' => $product->stock]
        );

        return $this->createdResponse(
            new ProductResource($product->fresh(['variants', 'images', 'specs', 'documents'])),
            'Product created successfully'
        );
    }

    /**
     * Update product details and optionally manage variants and specs.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'usage_instructions' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.variant_label' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sale_starts_at' => ['nullable', 'date'],
            'variants.*.sale_ends_at' => ['nullable', 'date'],
            'variants.*.stock' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'specs' => ['nullable', 'array'],
            'specs.*.id' => ['nullable', 'integer'],
            'specs.*.label' => ['required_with:specs', 'string', 'max:100'],
            'specs.*.value' => ['required_with:specs', 'string'],
            'specs.*.sort_order' => ['nullable', 'integer'],
        ]);

        $productData = collect($validated)->except(['variants', 'specs'])->toArray();

        // Sanitize rich text inputs
        if (array_key_exists('description', $productData)) {
            $productData['description'] = RichTextSanitizer::sanitize($productData['description']);
            if (empty($productData['short_description']) && $productData['description']) {
                $productData['short_description'] = Str::limit(strip_tags($productData['description']), 155);
            }
        }
        if (array_key_exists('usage_instructions', $productData)) {
            $productData['usage_instructions'] = RichTextSanitizer::sanitize($productData['usage_instructions']);
        }

        $product->update($productData);

        // Update specifications
        if (isset($validated['specs']) && is_array($validated['specs'])) {
            $product->specs()->delete();
            foreach ($validated['specs'] as $idx => $spec) {
                if (! empty($spec['label']) && ! empty($spec['value'])) {
                    $product->specs()->create([
                        'label' => trim($spec['label']),
                        'value' => trim($spec['value']),
                        'sort_order' => $spec['sort_order'] ?? $idx,
                    ]);
                }
            }
        }

        if (isset($validated['variants']) && is_array($validated['variants'])) {
            $existingIds = [];
            $hasDefault = false;

            foreach ($validated['variants'] as $idx => $v) {
                $isDef = ! empty($v['is_default']);
                if ($isDef && ! $hasDefault) {
                    $hasDefault = true;
                } elseif ($isDef && $hasDefault) {
                    $isDef = false;
                }

                if (! empty($v['id'])) {
                    $variant = $product->variants()->where('id', $v['id'])->first();
                    if ($variant) {
                        $variant->update([
                            'variant_label' => $v['variant_label'],
                            'sku' => $v['sku'] ?? $variant->sku,
                            'price' => $v['price'],
                            'compare_at_price' => array_key_exists('compare_at_price', $v) ? $v['compare_at_price'] : $variant->compare_at_price,
                            'sale_starts_at' => array_key_exists('sale_starts_at', $v) ? $v['sale_starts_at'] : $variant->sale_starts_at,
                            'sale_ends_at' => array_key_exists('sale_ends_at', $v) ? $v['sale_ends_at'] : $variant->sale_ends_at,
                            'stock' => $v['stock'],
                            'low_stock_threshold' => $v['low_stock_threshold'] ?? 10,
                            'is_default' => $isDef,
                        ]);
                        $existingIds[] = $variant->id;
                    }
                } else {
                    $vSlug = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $v['variant_label'] ?? ''), '-'));
                    $newVar = $product->variants()->create([
                        'variant_label' => $v['variant_label'],
                        'sku' => ! empty($v['sku']) ? $v['sku'] : ('PRD-' . $product->id . '-' . ($vSlug ?: ('VAR' . ($idx + 1)))),
                        'price' => $v['price'],
                        'compare_at_price' => $v['compare_at_price'] ?? null,
                        'sale_starts_at' => $v['sale_starts_at'] ?? null,
                        'sale_ends_at' => $v['sale_ends_at'] ?? null,
                        'stock' => $v['stock'],
                        'low_stock_threshold' => $v['low_stock_threshold'] ?? 10,
                        'is_default' => $isDef,
                    ]);
                    $existingIds[] = $newVar->id;
                }
            }

            // Remove omitted variants that have no order items attached
            $product->variants()->whereNotIn('id', $existingIds)->delete();

            if (! $hasDefault && $product->variants()->count() > 0) {
                $product->variants()->first()->update(['is_default' => true]);
            }

            $product->syncAggregateStockAndPrice();
        }

        \App\Models\ActivityLog::log(
            'update_product',
            $product,
            ['changes' => $validated]
        );

        return $this->successResponse(
            new ProductResource($product->fresh(['variants', 'images'])),
            'Product updated successfully'
        );
    }

    /**
     * Adjust product stock quantity with an audit reason/note.
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $hasMultiple = $product->has_multiple_variants;

        $validated = $request->validate([
            'product_variant_id' => [$hasMultiple ? 'required' : 'nullable', 'integer', 'exists:product_variants,id'],
            'stock' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $updatedProduct = DB::transaction(function () use ($request, $product, $validated) {
            $variantId = $validated['product_variant_id'] ?? null;
            $variant = $variantId
                ? $product->variants()->findOrFail($variantId)
                : ($product->defaultVariant ?? $product->variants()->first());

            if (! $variant) {
                $variant = $product->variants()->create([
                    'variant_label' => 'Standard',
                    'sku' => 'PRD-' . $product->id . '-STANDARD',
                    'price' => $product->price,
                    'stock' => 0,
                    'is_default' => true,
                ]);
            }

            $oldStock = (int) $variant->stock;
            $newStock = (int) $validated['stock'];
            $adjustment = $newStock - $oldStock;

            $variant->update(['stock' => $newStock]);
            $product->syncAggregateStockAndPrice();

            ProductStockAdjustment::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'user_id' => $request->user()->id,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'adjustment' => $adjustment,
                'reason' => $validated['reason'],
            ]);

            \App\Models\ActivityLog::log(
                'adjust_stock',
                $product,
                [
                    'variant_id' => $variant->id,
                    'variant_label' => $variant->variant_label,
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'reason' => $validated['reason'],
                ]
            );

            return $product;
        });

        return $this->successResponse(
            new ProductResource($updatedProduct->fresh(['variants', 'images', 'specs', 'documents'])),
            'Product stock adjusted successfully'
        );
    }

    /**
     * Activate or deactivate a product.
     */
    public function updateStatus(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $product->update([
            'is_active' => $validated['is_active'],
        ]);

        \App\Models\ActivityLog::log(
            $product->is_active ? 'activate_product' : 'deactivate_product',
            $product,
            ['name' => $product->name]
        );

        $statusText = $product->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            new ProductResource($product->fresh()),
            "Product {$statusText} successfully"
        );
    }

    /**
     * Upload an image for a product.
     */
    public function uploadImage(\App\Http\Requests\Api\V1\UploadImageRequest $request, Product $product, \App\Services\ImageUploadService $uploader): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $file = $request->getImageFile();
        $result = $uploader->storeImageWithThumbnail($file, 'products');

        $product->update([
            'image_path' => $result['path'],
            'image_thumbnail_path' => $result['thumbnail_path'],
            'image_url' => $result['url'],
        ]);

        // Keep product_images gallery in sync with primary image
        $existingPrimary = $product->images()->where('is_primary', true)->first();
        if ($existingPrimary) {
            $existingPrimary->update([
                'image_path' => $result['path'],
            ]);
        } else {
            $product->images()->create([
                'image_path' => $result['path'],
                'sort_order' => 0,
                'is_primary' => true,
            ]);
        }

        \App\Models\ActivityLog::log(
            'update_product_image',
            $product,
            ['name' => $product->name, 'path' => $result['path']]
        );

        return $this->successResponse([
            'path' => $result['path'],
            'thumbnail_path' => $result['thumbnail_path'],
            'image_url' => $result['url'],
            'image_thumbnail_url' => $result['thumbnail_url'],
            'product' => new ProductResource($product->fresh(['variants', 'images'])),
        ], 'Product image uploaded successfully');
    }

    /**
     * Upload an additional gallery image for a product.
     */
    public function uploadGalleryImage(\App\Http\Requests\Api\V1\UploadImageRequest $request, Product $product, \App\Services\ImageUploadService $uploader): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $file = $request->getImageFile();
        $result = $uploader->storeImageWithThumbnail($file, 'products');

        $isFirst = $product->images()->count() === 0;
        $maxSort = (int) $product->images()->max('sort_order');

        $image = $product->images()->create([
            'image_path' => $result['path'],
            'sort_order' => $maxSort + 1,
            'is_primary' => $isFirst,
        ]);

        if ($isFirst) {
            $product->update([
                'image_path' => $result['path'],
                'image_thumbnail_path' => $result['thumbnail_path'],
                'image_url' => $result['url'],
            ]);
        }

        \App\Models\ActivityLog::log(
            'upload_gallery_image',
            $product,
            ['name' => $product->name, 'path' => $result['path']]
        );

        return $this->successResponse([
            'image' => new \App\Http\Resources\V1\ProductImageResource($image),
            'product' => new ProductResource($product->fresh(['variants', 'images', 'specs', 'documents'])),
        ], 'Gallery image uploaded successfully');
    }

    /**
     * Upload an inline image for rich text editor descriptions.
     */
    public function uploadEditorImage(\App\Http\Requests\Api\V1\UploadImageRequest $request, \App\Services\ImageUploadService $uploader): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $file = $request->getImageFile();
        $result = $uploader->storeImageWithThumbnail($file, 'products/editor');

        return $this->successResponse([
            'url' => $result['url'],
            'path' => $result['path'],
            'thumbnail_url' => $result['thumbnail_url'],
        ], 'Editor image uploaded successfully');
    }

    /**
     * Delete a gallery image from a product.
     */
    public function deleteGalleryImage(Product $product, \App\Models\ProductImage $image): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        if ($image->product_id !== $product->id) {
            abort(404, 'Image does not belong to this product.');
        }

        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            $next = $product->images()->orderBy('sort_order')->first();
            if ($next) {
                $next->update(['is_primary' => true]);
                $product->update([
                    'image_path' => $next->image_path,
                ]);
            } else {
                $product->update([
                    'image_path' => null,
                    'image_thumbnail_path' => null,
                    'image_url' => null,
                ]);
            }
        }

        return $this->successResponse(
            new ProductResource($product->fresh(['variants', 'images', 'specs', 'documents'])),
            'Gallery image deleted successfully'
        );
    }

    /**
     * Reorder gallery images or set a primary image.
     */
    public function reorderGalleryImages(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $validated = $request->validate([
            'primary_id' => ['nullable', 'integer'],
            'order' => ['nullable', 'array'],
            'order.*.id' => ['required', 'integer'],
            'order.*.sort_order' => ['required', 'integer'],
        ]);

        if (! empty($validated['order'])) {
            foreach ($validated['order'] as $item) {
                $product->images()->where('id', $item['id'])->update([
                    'sort_order' => $item['sort_order'],
                ]);
            }
        }

        if (! empty($validated['primary_id'])) {
            $product->images()->update(['is_primary' => false]);
            $primary = $product->images()->where('id', $validated['primary_id'])->first();
            if ($primary) {
                $primary->update(['is_primary' => true]);
                $product->update([
                    'image_path' => $primary->image_path,
                ]);
            }
        }

        return $this->successResponse(
            new ProductResource($product->fresh(['variants', 'images', 'specs', 'documents'])),
            'Gallery images updated successfully'
        );
    }

    /**
     * Toggle the featured flag on a product.
     */
    public function toggleFeatured(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $validated = $request->validate([
            'is_featured' => ['required', 'boolean'],
        ]);

        $product->update([
            'is_featured' => $validated['is_featured'],
        ]);

        \App\Models\ActivityLog::log(
            $product->is_featured ? 'feature_product' : 'unfeature_product',
            $product,
            ['name' => $product->name]
        );

        $statusText = $product->is_featured ? 'featured' : 'unfeatured';

        return $this->successResponse(
            new ProductResource($product->fresh(['variants', 'images', 'specs', 'documents'])),
            "Product {$statusText} successfully"
        );
    }

    /**
     * Upload a PDF document (datasheet, safety sheet, certificate) for a product.
     */
    public function uploadDocument(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $fileKey = $request->hasFile('file') ? 'file' : ($request->hasFile('document') ? 'document' : 'file');

        $validated = $request->validate([
            $fileKey => ['required', 'file', 'mimes:pdf', 'max:10240'], // 10MB PDF limit
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'in:datasheet,safety,certificate'],
        ]);

        $file = $request->file($fileKey);
        $path = $file->store("products/{$product->id}/documents", 'public');

        $doc = $product->documents()->create([
            'title' => $validated['title'],
            'file_path' => $path,
            'type' => $validated['type'],
            'file_size_bytes' => $file->getSize(),
        ]);

        \App\Models\ActivityLog::log(
            'upload_product_document',
            $product,
            ['title' => $doc->title, 'type' => $doc->type, 'path' => $path]
        );

        $docResource = (new ProductDocumentResource($doc))->toArray($request);
        $docResource['product'] = new ProductResource($product->fresh(['variants', 'images', 'specs', 'documents']));

        return $this->createdResponse(
            $docResource,
            'Product document uploaded successfully'
        );
    }

    /**
     * Delete a product document.
     */
    public function deleteDocument(Product $product, ProductDocument $document): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        if ($document->product_id !== $product->id) {
            abort(404, 'Document does not belong to this product.');
        }

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return $this->successResponse(
            new ProductResource($product->fresh(['variants', 'images', 'specs', 'documents'])),
            'Product document deleted successfully'
        );
    }

    /**
     * Update gallery image alt text or attributes.
     */
    public function updateGalleryImage(Request $request, Product $product, ProductImage $image): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        if ($image->product_id !== $product->id) {
            abort(404, 'Image does not belong to this product.');
        }

        $validated = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $image->update([
            'alt_text' => $validated['alt_text'] ?? null,
        ]);

        return $this->successResponse(
            new ProductResource($product->fresh(['variants', 'images', 'specs', 'documents'])),
            'Gallery image updated successfully'
        );
    }

    /**
     * Duplicate an existing product with its variants and specs.
     */
    public function duplicate(Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $product->load(['variants', 'specs']);

        $newProduct = DB::transaction(function () use ($product) {
            $clone = $product->replicate([
                'created_at',
                'updated_at',
            ]);
            $clone->name = $product->name . ' (Copy)';
            $clone->is_active = false; // Duplicated product starts as draft
            $clone->is_featured = false;
            $clone->save();

            // Duplicate variants with new unique SKUs
            foreach ($product->variants as $variant) {
                $code = 'PRD-' . str_pad($clone->id, 4, '0', STR_PAD_LEFT);
                $varSlug = Str::slug($variant->variant_label ?: 'var');
                $newSku = strtoupper("{$code}-{$varSlug}");

                $clone->variants()->create([
                    'variant_label' => $variant->variant_label,
                    'sku' => $newSku,
                    'price' => $variant->price,
                    'stock' => $variant->stock,
                    'low_stock_threshold' => $variant->low_stock_threshold,
                    'is_default' => $variant->is_default,
                ]);
            }

            // Duplicate specs
            foreach ($product->specs as $spec) {
                $clone->specs()->create([
                    'label' => $spec->label,
                    'value' => $spec->value,
                    'sort_order' => $spec->sort_order,
                ]);
            }

            \App\Models\ActivityLog::log(
                'duplicate_product',
                $clone,
                ['original_product_id' => $product->id, 'name' => $clone->name]
            );

            return $clone;
        });

        return $this->createdResponse(
            new ProductResource($newProduct->fresh(['variants', 'images', 'specs', 'documents'])),
            'Product duplicated successfully'
        );
    }
}
