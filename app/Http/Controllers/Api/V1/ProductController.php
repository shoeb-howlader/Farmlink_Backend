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
            $featured = Product::where('is_active', true)
                ->where('is_featured', true)
                ->where('stock', '>', 0)
                ->latest()
                ->take(4)
                ->get();

            // Fallback: if fewer than 4 featured products, fill with in-stock
            if ($featured->count() < 4) {
                $excludeIds = $featured->pluck('id');
                $fillers = Product::where('is_active', true)
                    ->where('stock', '>', 0)
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

        $query = Product::where('is_active', true);

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        $products = $query->latest()->get();

        return $this->successResponse(
            ProductResource::collection($products),
            'Products retrieved successfully'
        );
    }
}
