<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends ApiController
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request): JsonResponse
    {
        // Featured products mode: return up to 4 editorially-chosen products,
        // falling back to any in-stock products if none are flagged.
        if ($request->boolean('featured')) {
            $stockScope = function ($q) {
                $q->where(function ($subQ) {
                    $subQ->whereHas('variants', function ($vq) {
                        $vq->where('stock', '>', 0);
                    })->orWhere(function ($fallbackQ) {
                        $fallbackQ->whereDoesntHave('variants')->where('stock', '>', 0);
                    });
                });
            };

            $featured = Product::with(['variants', 'images', 'specs', 'documents'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->where('is_active', true)
                ->where('is_featured', true)
                ->where($stockScope)
                ->latest()
                ->take(4)
                ->get();

            // Fallback: if fewer than 4 featured products, fill with in-stock
            if ($featured->count() < 4) {
                $excludeIds = $featured->pluck('id');
                $fillers = Product::with(['variants', 'images', 'specs', 'documents'])
                    ->withAvg('reviews', 'rating')
                    ->withCount('reviews')
                    ->where('is_active', true)
                    ->where($stockScope)
                    ->whereNotIn('id', $excludeIds)
                    ->latest()
                    ->take(4 - $featured->count())
                    ->get();

                $featured = $featured->concat($fillers);
            }

            return $this->successResponse(
                ProductResource::collection($featured),
                'Featured products retrieved successfully'
            );
        }

        $query = Product::where('is_active', true)
            ->with(['variants', 'images', 'specs', 'documents'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($request->filled('category')) {
            $catInput = strtolower(trim((string) $request->query('category')));
            $categoryMap = [
                'feed' => 'Feed',
                'medicine' => 'Medicine',
                'equipment' => 'Equipment',
                'chemicals' => 'Chemicals',
                'probiotics' => 'Probiotics',
            ];

            if (isset($categoryMap[$catInput])) {
                $query->where('category', $categoryMap[$catInput]);
            } else {
                $query->whereRaw('LOWER(category) = ?', [$catInput]);
            }
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('in_stock')) {
            $query->where(function ($subQ) {
                $subQ->whereHas('variants', function ($vq) {
                    $vq->where('stock', '>', 0);
                })->orWhere(function ($fallbackQ) {
                    $fallbackQ->whereDoesntHave('variants')->where('stock', '>', 0);
                });
            });
        }

        $sort = $request->query('sort');
        if ($sort === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('price', 'desc');
        } elseif ($sort === 'name_asc') {
            $query->orderBy('name', 'asc');
        } else {
            $query->latest();
        }

        $limit = max(1, min((int) ($request->query('per_page') ?? 24), 50));
        $paginated = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => ProductResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): JsonResponse
    {
        abort_if(! $product->is_active, 404, 'Product not found or inactive.');

        $product->load(['variants', 'images', 'specs', 'documents', 'reviews.user']);

        return $this->successResponse(
            new ProductResource($product),
            'Product retrieved successfully'
        );
    }
}
