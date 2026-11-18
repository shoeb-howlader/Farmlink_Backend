<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceFeeController extends ApiController
{
    /**
     * Display a listing of in-store services, lab diagnostic tests, and consultancy fees.
     */
    public function index(): JsonResponse
    {
        $services = Product::where('category', 'Services')
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse($services);
    }

    /**
     * Store a newly created service or diagnostic test fee.
     */
    public function store(Request $request): JsonResponse
    {
        $staff = $request->user();
        if (! $staff || ! $staff->hasAnyRole(['admin', 'data_entry_operator'])) {
            return $this->errorResponse('Unauthorized to manage service fees', 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'short_description_bn' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_bn' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $service = Product::create([
            'name' => trim($validated['name']),
            'name_bn' => ! empty($validated['name_bn']) ? trim($validated['name_bn']) : null,
            'category' => 'Services',
            'category_bn' => 'ল্যাব টেস্ট ও সেবা',
            'price' => (float) $validated['price'],
            'stock' => 999999, // Virtual unlimited stock for billing
            'low_stock_threshold' => 0,
            'is_active' => $validated['is_active'] ?? true,
            'short_description' => $validated['short_description'] ?? null,
            'short_description_bn' => $validated['short_description_bn'] ?? null,
            'description' => $validated['description'] ?? null,
            'description_bn' => $validated['description_bn'] ?? null,
        ]);

        ActivityLog::log(
            'service_fee.created',
            $service,
            [
                'name' => $service->name,
                'price' => $service->price,
                'is_active' => $service->is_active,
            ],
            $staff
        );

        return $this->createdResponse($service, 'Service fee created successfully');
    }

    /**
     * Update an existing service or diagnostic test fee.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $staff = $request->user();
        if (! $staff || ! $staff->hasAnyRole(['admin', 'data_entry_operator'])) {
            return $this->errorResponse('Unauthorized to manage service fees', 403);
        }

        $service = Product::where('category', 'Services')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'short_description_bn' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_bn' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $service->update(array_filter([
            'name' => isset($validated['name']) ? trim($validated['name']) : null,
            'name_bn' => array_key_exists('name_bn', $validated) ? trim($validated['name_bn'] ?? '') : null,
            'price' => isset($validated['price']) ? (float) $validated['price'] : null,
            'short_description' => array_key_exists('short_description', $validated) ? $validated['short_description'] : null,
            'short_description_bn' => array_key_exists('short_description_bn', $validated) ? $validated['short_description_bn'] : null,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : null,
            'description_bn' => array_key_exists('description_bn', $validated) ? $validated['description_bn'] : null,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null,
        ], fn ($val) => $val !== null));

        ActivityLog::log(
            'service_fee.updated',
            $service,
            [
                'name' => $service->name,
                'price' => $service->price,
                'is_active' => $service->is_active,
            ],
            $staff
        );

        return $this->successResponse($service, 'Service fee updated successfully');
    }

    /**
     * Toggle active status of a service fee.
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $staff = $request->user();
        if (! $staff || ! $staff->hasAnyRole(['admin', 'data_entry_operator'])) {
            return $this->errorResponse('Unauthorized to manage service fees', 403);
        }

        $service = Product::where('category', 'Services')->findOrFail($id);
        $service->is_active = ! $service->is_active;
        $service->save();

        ActivityLog::log(
            'service_fee.toggled',
            $service,
            [
                'name' => $service->name,
                'is_active' => $service->is_active,
            ],
            $staff
        );

        return $this->successResponse($service, 'Service fee status toggled successfully');
    }

    /**
     * Delete or deactivate a service fee.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $staff = $request->user();
        if (! $staff || ! $staff->hasAnyRole(['admin'])) {
            return $this->errorResponse('Unauthorized to delete service fees', 403);
        }

        $service = Product::where('category', 'Services')->findOrFail($id);

        // Soft deactivate if referenced in orders, or delete
        $hasOrderItems = \App\Models\OrderItem::where('product_id', $service->id)->exists();
        if ($hasOrderItems) {
            $service->is_active = false;
            $service->save();
            $message = 'Service fee has prior order records; disabled instead of deleted.';
        } else {
            $service->delete();
            $message = 'Service fee deleted successfully.';
        }

        ActivityLog::log(
            'service_fee.deleted',
            $service,
            ['name' => $service->name],
            $staff
        );

        return $this->successResponse(null, $message);
    }
}
