<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Return public dynamic URLs for Nuxt Sitemap module.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $urls = Cache::remember('sitemap_public_urls', 1800, function () {
            $routes = [];

            // Public Active Products
            $products = Product::query()
                ->where('is_active', true)
                ->select(['id', 'updated_at'])
                ->orderBy('updated_at', 'desc')
                ->get();

            foreach ($products as $product) {
                $routes[] = [
                    'loc' => '/products/' . $product->id,
                    'lastmod' => $product->updated_at ? $product->updated_at->toIso8601String() : now()->toIso8601String(),
                    'changefreq' => 'weekly',
                    'priority' => 0.8,
                ];
            }

            return $routes;
        });

        return response()->json($urls);
    }
}
