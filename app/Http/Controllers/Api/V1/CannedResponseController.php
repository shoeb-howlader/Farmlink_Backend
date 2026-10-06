<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CannedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CannedResponseController extends ApiController
{
    /**
     * Get canned responses available for the current user's role.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(['admin', 'data_entry_operator', 'deo']);
        $isPractitioner = $user->hasAnyRole(['veterinary_doctor', 'veterinarian', 'consultant']);

        $query = CannedResponse::query()->where('is_active', true);

        if ($request->filled('scope')) {
            $query->where('scope', $request->query('scope'));
        } elseif ($isStaff) {
            $query->where('scope', 'admin');
        } elseif ($isPractitioner) {
            $query->where('scope', 'practitioner');
        } else {
            // General fallback
            $query->where('scope', 'admin');
        }

        $responses = $query->latest()->get();

        return $this->successResponse($responses, 'Canned responses retrieved successfully.');
    }

    /**
     * Create a new canned response.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:admin,practitioner'],
            'shortcut' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $shortcut = trim($validated['shortcut']);
        if (!str_starts_with($shortcut, '/')) {
            $shortcut = '/' . $shortcut;
        }

        $canned = CannedResponse::create([
            'user_id' => $request->user()->id,
            'scope' => $validated['scope'],
            'shortcut' => $shortcut,
            'title' => trim($validated['title']),
            'content' => trim($validated['content']),
            'is_active' => true,
        ]);

        return $this->createdResponse($canned, 'Canned response created successfully.');
    }

    /**
     * Update an existing canned response.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $canned = CannedResponse::findOrFail($id);

        $validated = $request->validate([
            'shortcut' => ['sometimes', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:150'],
            'content' => ['sometimes', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'scope' => ['sometimes', 'string', 'in:admin,practitioner'],
        ]);

        if (isset($validated['shortcut'])) {
            $shortcut = trim($validated['shortcut']);
            if (!str_starts_with($shortcut, '/')) {
                $shortcut = '/' . $shortcut;
            }
            $validated['shortcut'] = $shortcut;
        }

        $canned->update($validated);

        return $this->successResponse($canned, 'Canned response updated successfully.');
    }

    /**
     * Delete a canned response.
     */
    public function destroy(int $id): JsonResponse
    {
        $canned = CannedResponse::findOrFail($id);
        $canned->delete();

        return $this->successResponse(null, 'Canned response removed successfully.');
    }
}
