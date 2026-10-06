<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\Product;
use App\Models\Taxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaxonomyController extends ApiController
{
    /**
     * Allowed editable taxonomy types.
     * Fixed system enums (Order status, Service Request status, User roles) are excluded by design.
     */
    protected array $allowedTypes = [
        'farm_type',
        'culture_type',
        'farming_system',
        'water_source',
        'facility',
        'product_category',
        'specialty_tag',
    ];

    /**
     * Display a listing of taxonomies, optionally filtered by type.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Taxonomy::query();

        if ($request->filled('type')) {
            $type = $request->query('type');
            if (in_array($type, $this->allowedTypes)) {
                $query->where('type', $type);
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $taxonomies = $query->orderBy('type')->orderBy('sort_order')->orderBy('name')->get();

        return $this->successResponse([
            'types' => $this->allowedTypes,
            'taxonomies' => $taxonomies,
        ]);
    }

    /**
     * Store a newly created taxonomy item.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in($this->allowedTypes)],
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'], '_')
            : Str::slug($validated['name'], '_');

        // Check uniqueness for (type, slug)
        $exists = Taxonomy::where('type', $validated['type'])->where('slug', $slug)->exists();
        if ($exists) {
            return $this->errorResponse("A taxonomy with code '{$slug}' already exists for type '{$validated['type']}'.", 422);
        }

        $validated['slug'] = $slug;
        $validated['sort_order'] = $validated['sort_order'] ?? (Taxonomy::where('type', $validated['type'])->max('sort_order') + 1);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $taxonomy = Taxonomy::create($validated);

        ActivityLog::log(
            'taxonomy.created',
            $taxonomy,
            [
                'type' => $taxonomy->type,
                'slug' => $taxonomy->slug,
                'name' => $taxonomy->name,
            ],
            $request->user()?->id
        );

        return $this->createdResponse($taxonomy, 'Taxonomy item created successfully');
    }

    /**
     * Update an existing taxonomy label / properties.
     */
    public function update(Request $request, Taxonomy $taxonomy): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $oldName = $taxonomy->name;
        $taxonomy->update($validated);

        ActivityLog::log(
            'taxonomy.updated',
            $taxonomy,
            [
                'type' => $taxonomy->type,
                'slug' => $taxonomy->slug,
                'old_name' => $oldName,
                'new_name' => $taxonomy->name,
                'is_active' => $taxonomy->is_active,
            ],
            $request->user()?->id
        );

        return $this->successResponse($taxonomy, 'Taxonomy item updated successfully');
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Request $request, Taxonomy $taxonomy): JsonResponse
    {
        $taxonomy->is_active = ! $taxonomy->is_active;
        $taxonomy->save();

        ActivityLog::log(
            'taxonomy.status_toggled',
            $taxonomy,
            [
                'type' => $taxonomy->type,
                'slug' => $taxonomy->slug,
                'is_active' => $taxonomy->is_active,
            ],
            $request->user()?->id
        );

        return $this->successResponse($taxonomy, 'Taxonomy item status toggled successfully');
    }

    /**
     * Remove a taxonomy item only if unreferenced.
     * Never hard-delete if referenced by existing records.
     */
    public function destroy(Request $request, Taxonomy $taxonomy): JsonResponse
    {
        $isReferenced = $this->checkIsReferenced($taxonomy);

        if ($isReferenced) {
            return $this->errorResponse(
                "Cannot delete '{$taxonomy->name}' because it is referenced by existing database records. Please deactivate it instead so it stops appearing in dropdowns while historical records remain intact.",
                422
            );
        }

        $taxonomy->delete();

        ActivityLog::log(
            'taxonomy.deleted',
            $taxonomy,
            [
                'type' => $taxonomy->type,
                'slug' => $taxonomy->slug,
                'name' => $taxonomy->name,
            ],
            $request->user()?->id
        );

        return $this->successResponse(null, 'Taxonomy item deleted successfully');
    }

    /**
     * Check whether a taxonomy slug is referenced by existing data.
     */
    protected function checkIsReferenced(Taxonomy $taxonomy): bool
    {
        $slug = $taxonomy->slug;

        return match ($taxonomy->type) {
            'farm_type' => Farm::where('farm_type', $slug)->exists(),
            'culture_type' => Farm::where('main_culture_type', $slug)->exists(),
            'farming_system' => Farm::where('farming_system', $slug)->exists(),
            'water_source' => Farm::where('main_water_source', $slug)->exists(),
            'facility' => Farm::whereJsonContains('available_facilities', $slug)->exists(),
            'product_category' => Product::where('category', $slug)->exists(),
            default => false,
        };
    }
}
