<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\V1\UserResource;
use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FarmerApprovalController extends ApiController
{
    /**
     * List farmers awaiting approval.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $query = User::role('farmer')
            ->where('status', 'pending_approval')
            ->with(['farms']);

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $pendingCount = User::role('farmer')->where('status', 'pending_approval')->count();
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->latest('phone_verified_at')->paginate($perPage);

        return $this->successResponse([
            'pending_count' => $pendingCount,
            'farmers' => UserResource::collection($paginated->getCollection()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ], 'Pending farmer approvals retrieved successfully');
    }

    /**
     * Get lightweight count of pending farmer approvals for sidebar badges.
     */
    public function count(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $pendingCount = User::role('farmer')
            ->where('status', 'pending_approval')
            ->count();

        return $this->successResponse([
            'pending_count' => $pendingCount,
        ], 'Pending farmer count retrieved successfully');
    }

    /**
     * Approve a self-registered farmer.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $farmer = User::role('farmer')->findOrFail($id);
        $oldStatus = $farmer->status;

        $farmer->update([
            'status' => 'active',
            'is_active' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'rejection_reason' => null,
        ]);

        // Audit Log
        ActivityLog::log(
            'farmer.approved',
            $farmer,
            [
                'farmer_id' => $farmer->id,
                'name' => $farmer->name,
                'phone' => $farmer->phone,
                'district' => $farmer->district,
                'status_before' => $oldStatus,
                'status_after' => 'active',
            ],
            $request->user()
        );

        // Notify Farmer
        AdminNotification::notify(
            'farmer.approved',
            'Account Approved!',
            'Your farmer account has been approved by Farmlink administration. You can now place supply orders and book specialist services.',
            ['farmer_id' => $farmer->id],
            $farmer->id
        );

        $remainingCount = User::role('farmer')->where('status', 'pending_approval')->count();

        return $this->successResponse([
            'farmer' => new UserResource($farmer->fresh(['farms', 'roles'])),
            'pending_count' => $remainingCount,
        ], "Farmer '{$farmer->name}' has been approved successfully.");
    }

    /**
     * Reject a self-registered farmer.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $farmer = User::role('farmer')->findOrFail($id);
        $oldStatus = $farmer->status;
        $reason = $validated['reason'] ?? null;

        $farmer->update([
            'status' => 'rejected',
            'is_active' => false,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
        ]);

        // Audit Log
        ActivityLog::log(
            'farmer.rejected',
            $farmer,
            [
                'farmer_id' => $farmer->id,
                'name' => $farmer->name,
                'phone' => $farmer->phone,
                'reason' => $reason,
                'status_before' => $oldStatus,
                'status_after' => 'rejected',
            ],
            $request->user()
        );

        // Notify Farmer
        $reasonSuffix = $reason ? " Reason: {$reason}" : '';
        AdminNotification::notify(
            'farmer.rejected',
            'Registration Application Update',
            "Your farmer account registration could not be approved at this time.{$reasonSuffix}",
            ['reason' => $reason],
            $farmer->id
        );

        $remainingCount = User::role('farmer')->where('status', 'pending_approval')->count();

        return $this->successResponse([
            'farmer' => new UserResource($farmer->fresh(['farms', 'roles'])),
            'pending_count' => $remainingCount,
        ], "Farmer '{$farmer->name}' application has been rejected.");
    }
}
