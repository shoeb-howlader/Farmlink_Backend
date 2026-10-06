<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\FarmCycleResource;
use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\FarmCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FarmCycleController extends ApiController
{
    /**
     * List all production cycles for the farm.
     */
    public function index(Request $request, Farm $farm): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasAnyRole(['data_entry_operator', 'deo']) && $farm->user_id !== $user->id) {
            return $this->errorResponse('Unauthorized to view cycles for this farm', 403);
        }

        $cycles = $farm->cycles()->with('creator')->get();

        return $this->successResponse(
            FarmCycleResource::collection($cycles),
            'Farm production cycles retrieved successfully'
        );
    }

    /**
     * Store a new production cycle for the farm.
     */
    public function store(Request $request, Farm $farm): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasAnyRole(['data_entry_operator', 'deo']) && $farm->user_id !== $user->id) {
            return $this->errorResponse('Unauthorized to create a cycle for this farm', 403);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $cycle = $farm->cycles()->create([
            'label' => $validated['label'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'created_by' => $user->id,
        ]);

        ActivityLog::log(
            'farm_cycle.created',
            $cycle,
            ['farm_id' => $farm->id, 'label' => $cycle->label, 'start_date' => $cycle->start_date],
            $user
        );

        return $this->createdResponse(
            new FarmCycleResource($cycle->load('creator')),
            'Farm production cycle created successfully'
        );
    }

    /**
     * Update an existing production cycle (e.g. close it with end_date).
     */
    public function update(Request $request, Farm $farm, FarmCycle $cycle): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasAnyRole(['data_entry_operator', 'deo']) && $farm->user_id !== $user->id) {
            return $this->errorResponse('Unauthorized to update cycles for this farm', 403);
        }

        if ($cycle->farm_id !== $farm->id) {
            return $this->errorResponse('Cycle does not belong to this farm', 404);
        }

        $validated = $request->validate([
            'label' => ['sometimes', 'string', 'max:100'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $cycle->update($validated);

        ActivityLog::log(
            'farm_cycle.updated',
            $cycle,
            ['farm_id' => $farm->id, 'updated_fields' => array_keys($validated)],
            $user
        );

        return $this->successResponse(
            new FarmCycleResource($cycle->fresh('creator')),
            'Farm production cycle updated successfully'
        );
    }
}
