<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\ProductReviewResource;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends ApiController
{
    /**
     * Display a listing of reviews for a product.
     */
    public function index(Request $request, Product $product): JsonResponse
    {
        $query = $product->reviews()
            ->with(['user:id,name,district']);

        $sort = $request->query('sort', 'newest');
        if ($sort === 'highest') {
            $query->orderBy('rating', 'desc')->latest();
        } elseif ($sort === 'lowest') {
            $query->orderBy('rating', 'asc')->latest();
        } else {
            $query->latest();
        }

        $reviews = $query->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Product reviews retrieved successfully',
            'data' => ProductReviewResource::collection($reviews->items()),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
                'rating_avg' => $product->rating_avg,
                'rating_count' => $product->rating_count,
                'rating_distribution' => $product->rating_distribution,
            ],
        ]);
    }

    /**
     * Store a newly created product review from an authenticated farmer.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = Order::findOrFail($validated['order_id']);

        // 1. Order must belong to authenticated user
        if ($order->user_id !== $user->id) {
            abort(403, 'You can only review products from your own orders.');
        }

        // 2. Order must be delivered
        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'order_id' => ["Products can only be reviewed after your order has been delivered. Current order status: {$order->status}"],
            ]);
        }

        // 3. Order must contain the specified product
        $containsProduct = $order->items()->where('product_id', $product->id)->exists();
        if (! $containsProduct) {
            throw ValidationException::withMessages([
                'product_id' => ['This order does not contain the specified product.'],
            ]);
        }

        // 4. Duplicate review prevention
        $existing = ProductReview::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('order_id', $order->id)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'rating' => ['You have already submitted a review for this product on this order.'],
            ]);
        }

        $review = ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        ActivityLog::log(
            'create_product_review',
            $review,
            [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'rating' => $review->rating,
                'order_id' => $order->id,
            ],
            $user
        );

        return $this->createdResponse(
            new ProductReviewResource($review->load('user')),
            'Thank you! Your product review has been submitted.'
        );
    }
}
