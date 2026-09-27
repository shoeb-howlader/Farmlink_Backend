<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Farm;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchController extends ApiController
{
    /**
     * Perform global search across farmers, farms, orders, and products (Admin only).
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $query = trim($request->query('q', ''));

        if (empty($query)) {
            return $this->successResponse([
                'farmers' => [],
                'farms' => [],
                'orders' => [],
                'products' => [],
            ], 'Search results');
        }

        // 1. Farmers (up to 5)
        $lowerQuery = mb_strtolower($query);
        $farmers = User::role('farmer')
            ->where(function ($q) use ($query, $lowerQuery) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$lowerQuery}%"])
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$lowerQuery}%"])
                    ->orWhereRaw('LOWER(district) LIKE ?', ["%{$lowerQuery}%"]);
            })
            ->limit(5)
            ->get(['id', 'name', 'phone', 'district', 'email'])
            ->map(function ($farmer) {
                return [
                    'id' => $farmer->id,
                    'title' => $farmer->name,
                    'subtitle' => $farmer->phone . ($farmer->district ? " • {$farmer->district}" : ''),
                    'url' => "/admin/farmers/{$farmer->id}",
                ];
            });

        // 2. Farms (up to 5)
        $farms = Farm::with('farmer')
            ->where(function ($q) use ($query) {
                $q->where('farm_name', 'like', "%{$query}%")
                    ->orWhere('district', 'like', "%{$query}%")
                    ->orWhere('upazila', 'like', "%{$query}%")
                    ->orWhereHas('farmer', function ($uq) use ($query) {
                        $uq->where('name', 'like', "%{$query}%");
                    });
            })
            ->limit(5)
            ->get()
            ->map(function ($farm) {
                $farmerName = $farm->farmer?->name ?? 'Unknown Farmer';
                return [
                    'id' => $farm->id,
                    'title' => $farm->farm_name,
                    'subtitle' => "{$farmerName} • {$farm->district}, {$farm->upazila} • {$farm->total_area} acres",
                    'url' => "/admin/farms/{$farm->id}",
                ];
            });

        // 3. Orders (up to 5)
        $orders = Order::with('user')
            ->where(function ($q) use ($query) {
                $q->where('id', $query)
                    ->orWhereHas('user', function ($uq) use ($query) {
                        $uq->where('name', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%");
                    });
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {
                $farmerName = $order->user?->name ?? 'Unknown Farmer';
                return [
                    'id' => $order->id,
                    'title' => "Order #ORD-{$order->id}",
                    'subtitle' => "{$farmerName} • ৳" . number_format((float) $order->total, 2) . " • " . ucfirst($order->status),
                    'url' => "/admin/orders/{$order->id}",
                ];
            });

        // 4. Products (up to 5)
        $products = Product::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->name,
                    'subtitle' => "{$product->category} • ৳" . number_format((float) $product->price, 2) . " • Stock: {$product->stock}",
                    'url' => '/admin/products',
                ];
            });

        return $this->successResponse([
            'farmers' => $farmers,
            'farms' => $farms,
            'orders' => $orders,
            'products' => $products,
        ], 'Search results');
    }
}
