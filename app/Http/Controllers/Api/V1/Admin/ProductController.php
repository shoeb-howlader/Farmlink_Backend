<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use App\Models\ProductStockAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductController extends ApiController
{
    /**
     * Display a paginated listing of all products for admin management.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $query = Product::query()->latest();

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
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $paginated = $query->paginate($perPage);

        $lowStockCount = Product::whereColumn('stock', '<=', 'low_stock_threshold')->count();

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
            ],
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
            'image_url' => $validated['image_url'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
        ]);

        \App\Models\ActivityLog::log(
            'create_product',
            $product,
            ['name' => $product->name, 'price' => $product->price, 'stock' => $product->stock]
        );

        return $this->createdResponse(
            new ProductResource($product),
            'Product created successfully'
        );
    }

    /**
     * Update product details (name, price, description, category, image_url, low_stock_threshold).
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $product->update($validated);

        \App\Models\ActivityLog::log(
            'update_product',
            $product,
            ['changes' => $validated]
        );

        return $this->successResponse(
            new ProductResource($product->fresh()),
            'Product updated successfully'
        );
    }

    /**
     * Adjust product stock quantity with an audit reason/note.
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('viewAdmin', \App\Models\Order::class);

        $validated = $request->validate([
            'stock' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $updatedProduct = DB::transaction(function () use ($request, $product, $validated) {
            $oldStock = $product->stock;
            $newStock = (int) $validated['stock'];
            $adjustment = $newStock - $oldStock;

            $product->update(['stock' => $newStock]);

            ProductStockAdjustment::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'adjustment' => $adjustment,
                'reason' => $validated['reason'],
            ]);

            \App\Models\ActivityLog::log(
                'adjust_stock',
                $product,
                ['old_stock' => $oldStock, 'new_stock' => $newStock, 'reason' => $validated['reason']]
            );

            return $product;
        });

        return $this->successResponse(
            new ProductResource($updatedProduct->fresh()),
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
            'product' => new ProductResource($product->fresh()),
        ], 'Product image uploaded successfully');
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
            new ProductResource($product->fresh()),
            "Product {$statusText} successfully"
        );
    }
}
