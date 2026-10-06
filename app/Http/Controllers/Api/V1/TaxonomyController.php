<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Taxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxonomyController extends ApiController
{
    /**
     * Get active taxonomies for public and authenticated forms (e.g. Farm form, Product filters).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('type')) {
            $taxonomies = Taxonomy::getByTypeCached($request->query('type'));
            return $this->successResponse($taxonomies)->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');
        }

        $grouped = Taxonomy::getActiveGroupedCached();

        return $this->successResponse($grouped)->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');
    }
}
