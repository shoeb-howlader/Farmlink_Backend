<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    /**
     * Display a listing of notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(['admin', 'data_entry_operator']);

        $baseQuery = AdminNotification::query();
        if ($isStaff) {
            $baseQuery->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $user->id);
            });
        } else {
            $baseQuery->where('user_id', $user->id);
        }

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
     * Get the unread notifications count for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(['admin', 'data_entry_operator']);

        $baseQuery = AdminNotification::query();
        if ($isStaff) {
            $baseQuery->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $user->id);
            });
        } else {
            $baseQuery->where('user_id', $user->id);
        }

        $unreadCount = $baseQuery->whereNull('read_at')->count();

        return $this->successResponse([
            'unread_count' => $unreadCount,
        ], 'Unread notification count retrieved successfully');
    }

    /**
     * Mark a single user notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(['admin', 'data_entry_operator']);

        $query = AdminNotification::where('id', $id);
        if ($isStaff) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $user->id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        $notification = $query->firstOrFail();
        $notification->markAsRead();

        $baseQuery = AdminNotification::query();
        if ($isStaff) {
            $baseQuery->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $user->id);
            });
        } else {
            $baseQuery->where('user_id', $user->id);
        }
        $unreadCount = $baseQuery->whereNull('read_at')->count();

        return $this->successResponse([
            'id' => $notification->id,
            'unread_count' => $unreadCount,
        ], 'Notification marked as read');
    }

    /**
     * Mark all notifications for the authenticated user as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(['admin', 'data_entry_operator']);

        $query = AdminNotification::whereNull('read_at');
        if ($isStaff) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $user->id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        $query->update(['read_at' => now()]);

        return $this->successResponse([
            'unread_count' => 0,
        ], 'All notifications marked as read');
    }
}
