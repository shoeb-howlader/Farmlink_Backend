<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends ApiController
{
    /**
     * Display a paginated listing of activity audit logs (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $query = ActivityLog::with('user:id,name,email');

        if ($request->filled('action') && $request->query('action') !== 'all') {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $paginated = $query->latest('id')->paginate($perPage);

        $data = $paginated->getCollection()->map(function (ActivityLog $log) {
            return [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'action' => $log->action,
                'subject_type' => class_basename($log->subject_type ?? ''),
                'subject_id' => $log->subject_id,
                'changes' => $log->changes,
                'created_at' => $log->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved successfully',
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Get recent audit logs for dashboard display.
     */
    public function recent(): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $logs = ActivityLog::with('user:id,name,email')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (ActivityLog $log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user?->name ?? 'System',
                    'action' => $log->action,
                    'subject_type' => class_basename($log->subject_type ?? ''),
                    'subject_id' => $log->subject_id,
                    'changes' => $log->changes,
                    'created_at' => $log->created_at?->toISOString(),
                ];
            });

        return $this->successResponse($logs, 'Recent activity logs retrieved successfully');
    }
}
