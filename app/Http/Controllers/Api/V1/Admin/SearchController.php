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
        $farmerModels = null;
        if (config('scout.driver') === 'meilisearch') {
            try {
                $farmerModels = User::search($query)
                    ->where('is_farmer', true)
                    ->take(5)
                    ->get();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Meilisearch admin farmer search failed: '.$e->getMessage());
            }
        }

        if ($farmerModels === null) {
            $farmerModels = User::role('farmer')
                ->where(function ($q) use ($query, $lowerQuery) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$lowerQuery}%"])
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$lowerQuery}%"])
                        ->orWhereRaw('LOWER(district) LIKE ?', ["%{$lowerQuery}%"]);
                })
                ->limit(5)
                ->get(['id', 'name', 'phone', 'district', 'email']);
        }

        $farmers = $farmerModels->map(function ($farmer) {
            return [
                'id' => $farmer->id,
                'title' => $farmer->name,
                'subtitle' => $farmer->phone . ($farmer->district ? " • {$farmer->district}" : ''),
                'url' => "/admin/farmers/{$farmer->id}",
            ];
        });

        // 2. Farms (up to 5)
        $farmModels = null;
        if (config('scout.driver') === 'meilisearch') {
            try {
                $farmModels = Farm::search($query)
                    ->query(fn ($q) => $q->with('farmer'))
                    ->take(5)
                    ->get();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Meilisearch admin farm search failed: '.$e->getMessage());
            }
        }

        if ($farmModels === null) {
            $farmModels = Farm::with('farmer')
                ->where(function ($q) use ($query, $lowerQuery) {
                    $q->whereRaw('LOWER(farm_name) LIKE ?', ["%{$lowerQuery}%"])
                        ->orWhereRaw('LOWER(district) LIKE ?', ["%{$lowerQuery}%"])
                        ->orWhereRaw('LOWER(upazila) LIKE ?', ["%{$lowerQuery}%"])
                        ->orWhereHas('farmer', function ($uq) use ($lowerQuery) {
                            $uq->whereRaw('LOWER(name) LIKE ?', ["%{$lowerQuery}%"]);
                        });
                })
                ->limit(5)
                ->get();
        }

        $farms = $farmModels->map(function ($farm) {
            $farmerName = $farm->farmer?->name ?? 'Unknown Farmer';
            return [
                'id' => $farm->id,
                'title' => $farm->farm_name,
                'subtitle' => "{$farmerName} • {$farm->district}, {$farm->upazila} • {$farm->total_area} acres",
                'url' => "/admin/farms/{$farm->id}",
            ];
        });

        // 3. Orders (up to 5)
        $orderModels = null;
        if (config('scout.driver') === 'meilisearch') {
            try {
                $orderModels = Order::search($query)
                    ->query(fn ($q) => $q->with('user'))
                    ->take(5)
                    ->get();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Meilisearch admin order search failed: '.$e->getMessage());
            }
        }

        if ($orderModels === null) {
            $cleanId = preg_replace('/[^0-9]/', '', $query);
            $orderModels = Order::with('user')
                ->where(function ($q) use ($query, $lowerQuery, $cleanId) {
                    if (!empty($cleanId) && strlen($cleanId) <= 18) {
                        $q->where('id', (int) $cleanId);
                    } else {
                        $q->whereRaw('1 = 0');
                    }

                    $q->orWhereHas('user', function ($uq) use ($query, $lowerQuery) {
                        $uq->whereRaw('LOWER(name) LIKE ?', ["%{$lowerQuery}%"])
                            ->orWhere('phone', 'like', "%{$query}%");
                    });
                })
                ->latest()
                ->limit(5)
                ->get();
        }

        $orders = $orderModels->map(function ($order) {
            $farmerName = $order->user?->name ?? 'Unknown Farmer';
            return [
                'id' => $order->id,
                'title' => "Order #ORD-{$order->id}",
                'subtitle' => "{$farmerName} • ৳" . number_format((float) $order->total, 2) . " • " . ucfirst($order->status),
                'url' => "/admin/orders/{$order->id}",
            ];
        });

        // 4. Products (up to 5)
        $productModels = null;
        if (config('scout.driver') === 'meilisearch') {
            try {
                $productModels = Product::search($query)->take(5)->get();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Meilisearch admin search failed: '.$e->getMessage());
            }
        }

        if ($productModels === null) {
            $productModels = Product::where(function ($q) use ($lowerQuery) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%{$lowerQuery}%"])
                        ->orWhereRaw('LOWER(category) LIKE ?', ["%{$lowerQuery}%"]);
                })
                ->limit(5)
                ->get();
        }

        $products = $productModels->map(function ($product) {
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
