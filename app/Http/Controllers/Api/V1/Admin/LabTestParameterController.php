<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabTestParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabTestParameterController extends Controller
{
    /**
     * List all diagnostic lab test parameters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LabTestParameter::query();

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_bn', 'like', "%{$search}%")
                    ->orWhere('default_unit', 'like', "%{$search}%")
                    ->orWhere('default_range', 'like', "%{$search}%");
            });
        }

        $parameters = $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Diagnostic test parameters retrieved successfully.',
            'data' => $parameters,
        ]);
    }

    /**
     * Store a newly created lab test parameter.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'category' => ['required', 'string', Rule::in(['water', 'soil', 'microbiology', 'other'])],
            'default_unit' => ['nullable', 'string', 'max:50'],
            'default_range' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! isset($validated['sort_order'])) {
            $validated['sort_order'] = (LabTestParameter::max('sort_order') ?? 0) + 1;
        }

        $parameter = LabTestParameter::create([
            'name' => trim($validated['name']),
            'name_bn' => ! empty($validated['name_bn']) ? trim($validated['name_bn']) : null,
            'category' => $validated['category'],
            'default_unit' => ! empty($validated['default_unit']) ? trim($validated['default_unit']) : null,
            'default_range' => ! empty($validated['default_range']) ? trim($validated['default_range']) : null,
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lab test parameter created successfully.',
            'data' => $parameter,
        ], 201);
    }

    /**
     * Update the specified lab test parameter.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $parameter = LabTestParameter::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'category' => ['required', 'string', Rule::in(['water', 'soil', 'microbiology', 'other'])],
            'default_unit' => ['nullable', 'string', 'max:50'],
            'default_range' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $parameter->update([
            'name' => trim($validated['name']),
            'name_bn' => ! empty($validated['name_bn']) ? trim($validated['name_bn']) : null,
            'category' => $validated['category'],
            'default_unit' => ! empty($validated['default_unit']) ? trim($validated['default_unit']) : null,
            'default_range' => ! empty($validated['default_range']) ? trim($validated['default_range']) : null,
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $parameter->sort_order,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : $parameter->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lab test parameter updated successfully.',
            'data' => $parameter->fresh(),
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggle(int $id): JsonResponse
    {
        $parameter = LabTestParameter::findOrFail($id);
        $parameter->is_active = ! $parameter->is_active;
        $parameter->save();

        return response()->json([
            'success' => true,
            'message' => 'Lab test parameter status updated successfully.',
            'data' => $parameter,
        ]);
    }

    /**
     * Delete a parameter.
     */
    public function destroy(int $id): JsonResponse
    {
        $parameter = LabTestParameter::findOrFail($id);
        $name = $parameter->name;
        $parameter->delete();

        return response()->json([
            'success' => true,
            'message' => "Parameter '{$name}' deleted successfully.",
        ]);
    }
}
