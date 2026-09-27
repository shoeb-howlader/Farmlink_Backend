<?php

namespace App\Http\Controllers\Api\V1\Deo;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeoPortalController extends ApiController
{
    /**
     * Display metrics and recent activity for the authenticated Data Entry Operator.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalFarmers = ActivityLog::where('user_id', $user->id)
            ->where('action', 'create_farmer')
            ->count();

        $totalFarms = ActivityLog::where('user_id', $user->id)
            ->where('action', 'farm.created')
            ->count();

        $posSalesLogs = ActivityLog::where('user_id', $user->id)
            ->where('action', 'order.pos_sale')
            ->get();

        $posCount = $posSalesLogs->count();
        $totalRevenue = 0;
        foreach ($posSalesLogs as $log) {
            $totalRevenue += (float) ($log->changes['total'] ?? 0);
        }

        // Recent activity feed logged by this DEO
        $recentActivity = ActivityLog::where('user_id', $user->id)
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(function (ActivityLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                    'changes' => $log->changes,
                    'created_at' => $log->created_at?->toISOString(),
                ];
            });

        // Recent farmers registered by this DEO
        $recentFarmerIds = ActivityLog::where('user_id', $user->id)
            ->where('action', 'create_farmer')
            ->latest('id')
            ->limit(5)
            ->pluck('subject_id');

        $recentFarmers = User::whereIn('id', $recentFarmerIds)
            ->withCount('farms')
            ->get()
            ->map(function (User $f) {
                return [
                    'id' => $f->id,
                    'name' => $f->name,
                    'phone' => $f->phone,
                    'district' => $f->district,
                    'farms_count' => $f->farms_count,
                    'created_at' => $f->created_at?->toISOString(),
                ];
            });

        return $this->successResponse([
            'metrics' => [
                'farmers_registered' => $totalFarmers,
                'farms_registered' => $totalFarms,
                'pos_sales_count' => $posCount,
                'pos_sales_revenue' => round($totalRevenue, 2),
            ],
            'recent_activity' => $recentActivity,
            'recent_farmers' => $recentFarmers,
            'deo_info' => [
                'id' => $user->id,
                'name' => $user->name,
                'district' => $user->district ?? 'Central',
                'phone' => $user->phone,
            ],
        ], 'DEO dashboard metrics retrieved successfully');
    }
}
