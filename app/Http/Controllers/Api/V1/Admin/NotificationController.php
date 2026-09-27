<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\AdminNotification;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends ApiController
{
    /**
     * Display a listing of admin notifications.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $user = $request->user();

        $baseQuery = AdminNotification::where(function ($q) use ($user) {
            $q->whereNull('user_id')
              ->orWhere('user_id', $user->id);
        });

        $query = (clone $baseQuery);

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $unreadCount = (clone $baseQuery)->whereNull('read_at')->count();
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginated = $query->latest('id')->paginate($perPage);

        return $this->successResponse([
            'unread_count' => $unreadCount,
            'notifications' => $paginated->getCollection()->map(function (AdminNotification $n) {
                return [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'message' => $n->message,
                    'data' => $n->data,
                    'is_read' => $n->isRead(),
                    'read_at' => $n->read_at?->toISOString(),
                    'created_at' => $n->created_at?->toISOString(),
                ];
            }),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ], 'Notifications retrieved successfully');
    }

    /**
     * Get the unread notifications count for admin.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $user = $request->user();

        $unreadCount = AdminNotification::where(function ($q) use ($user) {
            $q->whereNull('user_id')
              ->orWhere('user_id', $user->id);
        })->whereNull('read_at')->count();

        return $this->successResponse([
            'unread_count' => $unreadCount,
        ], 'Admin unread notification count retrieved successfully');
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $user = $request->user();

        $notification = AdminNotification::where(function ($q) use ($user) {
            $q->whereNull('user_id')
              ->orWhere('user_id', $user->id);
        })->findOrFail($id);

        $notification->markAsRead();

        $unreadCount = AdminNotification::where(function ($q) use ($user) {
            $q->whereNull('user_id')
              ->orWhere('user_id', $user->id);
        })->whereNull('read_at')->count();

        return $this->successResponse([
            'id' => $notification->id,
            'unread_count' => $unreadCount,
        ], 'Notification marked as read');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $user = $request->user();

        AdminNotification::where(function ($q) use ($user) {
            $q->whereNull('user_id')
              ->orWhere('user_id', $user->id);
        })->whereNull('read_at')->update(['read_at' => now()]);

        return $this->successResponse([
            'unread_count' => 0,
        ], 'All notifications marked as read');
    }
}
