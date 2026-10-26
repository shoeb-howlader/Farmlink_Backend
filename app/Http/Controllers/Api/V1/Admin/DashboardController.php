<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Farm;
use App\Models\Order;
use App\Models\Product;
use App\Models\RescheduleRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardController extends ApiController
{
    /**
     * Provide a unified, high-performance aggregated summary for the Admin Dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $now = Carbon::now();
        $excludedStatuses = ['cancelled', 'rejected', 'returned'];

        // 1. Date Range Handling
        $range = $request->query('range', 'all');
        $rangeStartDate = null;
        $rangeEndDate = $now->copy()->endOfDay();

        if ($range === 'today') {
            $rangeStartDate = $now->copy()->startOfDay();
        } elseif ($range === 'yesterday') {
            $rangeStartDate = $now->copy()->subDay()->startOfDay();
            $rangeEndDate = $now->copy()->subDay()->endOfDay();
        } elseif ($range === '7d') {
            $rangeStartDate = $now->copy()->subDays(6)->startOfDay();
        } elseif ($range === '30d') {
            $rangeStartDate = $now->copy()->subDays(29)->startOfDay();
        } elseif ($range === 'this_month') {
            $rangeStartDate = $now->copy()->startOfMonth();
        }

        $summaryData = \Illuminate\Support\Facades\Cache::remember("admin_dashboard_summary_{$range}", 300, function () use ($range, $now, $excludedStatuses, $rangeStartDate, $rangeEndDate) {
            // 2. High-Performance Consolidated SQL Aggregations for Sales
            $startOfMonth = $now->copy()->startOfMonth()->toDateTimeString();
            $endOfMonth = $now->copy()->endOfMonth()->toDateTimeString();
            $startOfLastMonth = $now->copy()->subMonth()->startOfMonth()->toDateTimeString();
            $endOfLastMonth = $now->copy()->subMonth()->endOfMonth()->toDateTimeString();

            $salesAgg = Order::selectRaw("
                count(*) as total_orders,
                coalesce(sum(case when status not in ('cancelled', 'rejected', 'returned') then total else 0 end), 0) as total_revenue,
                count(case when status not in ('cancelled', 'rejected', 'returned') then 1 end) as valid_orders,
                count(case when created_at >= ? and created_at <= ? then 1 end) as this_month_orders,
                coalesce(sum(case when status not in ('cancelled', 'rejected', 'returned') and created_at >= ? and created_at <= ? then total else 0 end), 0) as this_month_revenue,
                count(case when created_at >= ? and created_at <= ? then 1 end) as last_month_orders,
                coalesce(sum(case when status not in ('cancelled', 'rejected', 'returned') and created_at >= ? and created_at <= ? then total else 0 end), 0) as last_month_revenue
            ", [$startOfMonth, $endOfMonth, $startOfMonth, $endOfMonth, $startOfLastMonth, $endOfLastMonth, $startOfLastMonth, $endOfLastMonth])->first();

            $totalOrders = (int) ($salesAgg->total_orders ?? 0);
            $totalRevenue = (float) ($salesAgg->total_revenue ?? 0);
            $thisMonthOrders = (int) ($salesAgg->this_month_orders ?? 0);
            $thisMonthRevenue = (float) ($salesAgg->this_month_revenue ?? 0);
            $lastMonthOrders = (int) ($salesAgg->last_month_orders ?? 0);
            $lastMonthRevenue = (float) ($salesAgg->last_month_revenue ?? 0);
            $validAllTimeOrders = (int) ($salesAgg->valid_orders ?? 0);
            $avgOrderValue = $validAllTimeOrders > 0 ? round($totalRevenue / $validAllTimeOrders, 2) : 0;

            $revenueDeltaPercent = $lastMonthRevenue > 0
                ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
                : ($thisMonthRevenue > 0 ? 100.0 : 0.0);

            $ordersDeltaPercent = $lastMonthOrders > 0
                ? round((($thisMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100, 1)
                : ($thisMonthOrders > 0 ? 100.0 : 0.0);

            // Filtered range metrics (if range was supplied)
            $rangeRevenue = $totalRevenue;
            $rangeOrders = $totalOrders;
            if ($rangeStartDate !== null) {
                $rangeAgg = Order::whereBetween('created_at', [$rangeStartDate, $rangeEndDate])
                    ->selectRaw("
                        count(*) as count,
                        coalesce(sum(case when status not in ('cancelled', 'rejected', 'returned') then total else 0 end), 0) as revenue
                    ")
                    ->first();
                $rangeOrders = (int) ($rangeAgg->count ?? 0);
                $rangeRevenue = (float) ($rangeAgg->revenue ?? 0);
            }

            // Fast status counts in 1 SQL query
            $rawStatusCounts = Order::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->all();

            $statusCounts = [
                'pending_payment' => (int) ($rawStatusCounts['pending_payment'] ?? 0),
                'pending' => (int) ($rawStatusCounts['pending'] ?? 0),
                'confirmed' => (int) ($rawStatusCounts['confirmed'] ?? 0),
                'dispatched' => (int) ($rawStatusCounts['dispatched'] ?? 0),
                'delivered' => (int) ($rawStatusCounts['delivered'] ?? 0),
                'cancelled' => (int) ($rawStatusCounts['cancelled'] ?? 0),
                'returned' => (int) ($rawStatusCounts['returned'] ?? 0),
            ];

            // 3. Daily Sales & Orders Velocity Trend (Last 14 days)
            $trendDays = $range === '30d' ? 30 : 14;
            $trendStart = $now->copy()->subDays($trendDays - 1)->startOfDay();

            $dailyStats = Order::where('created_at', '>=', $trendStart)
                ->whereNotIn('status', $excludedStatuses)
                ->select(
                    DB::raw('DATE(created_at) as order_date'),
                    DB::raw('COUNT(*) as total_count'),
                    DB::raw('SUM(total) as daily_revenue')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->get()
                ->keyBy('order_date');

            $dailyFarmers = User::role('farmer')
                ->where('created_at', '>=', $trendStart)
                ->select(
                    DB::raw('DATE(created_at) as signup_date'),
                    DB::raw('COUNT(*) as total_count')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->get()
                ->keyBy('signup_date');

            $salesTrend = [];
            for ($i = 0; $i < $trendDays; $i++) {
                $dateCursor = $trendStart->copy()->addDays($i);
                $dateKey = $dateCursor->toDateString();
                $label = $dateCursor->format('d M');
                $record = $dailyStats->get($dateKey);
                $farmerRecord = $dailyFarmers->get($dateKey);

                $salesTrend[] = [
                    'date' => $dateKey,
                    'label' => $label,
                    'revenue' => (float) ($record?->daily_revenue ?? 0),
                    'orders_count' => (int) ($record?->total_count ?? 0),
                    'farmers_count' => (int) ($farmerRecord?->total_count ?? 0),
                ];
            }

            // 4. Action Center / Operational Alerts
            $urgentServiceRequests = ServiceRequest::whereIn('status', ['pending', 'assigned', 'in_progress'])
                ->where('urgency', 'urgent')
                ->count();

            $pendingOrders = (int) (($statusCounts['pending'] ?? 0) + ($statusCounts['pending_payment'] ?? 0));

            $lowStockCount = Product::where('is_active', true)->where(function ($q) {
                $q->whereColumn('stock', '<=', 'low_stock_threshold')
                  ->orWhereHas('variants', function ($qv) {
                      $qv->whereColumn('stock', '<=', 'low_stock_threshold');
                  });
            })->count();

            $pendingRescheduleRequests = RescheduleRequest::where('status', 'pending')->count();

            $todayString = $now->toDateString();
            $overdueFollowUpsCount = Farm::where(function ($query) use ($todayString) {
                $query->whereHas('vetRecords', function ($q) use ($todayString) {
                    $q->whereNotNull('next_follow_up')->where('next_follow_up', '<', $todayString);
                })->orWhereHas('consultantRecords', function ($q) use ($todayString) {
                    $q->whereNotNull('next_follow_up')->where('next_follow_up', '<', $todayString);
                });
            })->count();

            // 5. Service & Field Operations Pulse
            $openServiceRequests = ServiceRequest::whereIn('status', ['pending', 'assigned', 'in_progress'])->count();
            $completedThisMonth = ServiceRequest::where('status', 'completed')
                ->where('completed_at', '>=', $now->copy()->startOfMonth())
                ->count();

            // Turnaround hours calculation
            $completedSample = ServiceRequest::where('status', 'completed')
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->limit(50)
                ->select(['id', 'created_at', 'completed_at'])
                ->get();

            $turnaroundHours = [];
            foreach ($completedSample as $req) {
                $c = Carbon::parse($req->created_at);
                $done = Carbon::parse($req->completed_at);
                if ($done->greaterThanOrEqualTo($c)) {
                    $turnaroundHours[] = $c->diffInHours($done);
                }
            }
            $avgTurnaroundHours = count($turnaroundHours) > 0 ? round(array_sum($turnaroundHours) / count($turnaroundHours), 1) : 0;

            $activePractitionersCount = User::role(['veterinary_doctor', 'consultant'])->where('is_active', true)->count();

            // Farm type service request breakdown
            $categoryBreakdown = ServiceRequest::join('farms', 'service_requests.farm_id', '=', 'farms.id')
                ->select('farms.farm_type', DB::raw('count(*) as count'))
                ->groupBy('farms.farm_type')
                ->get()
                ->map(function ($row) {
                    return [
                        'farm_type' => $row->farm_type ?: 'general',
                        'count' => (int) $row->count,
                    ];
                })
                ->values()
                ->all();

            // 6. Critical Low Stock Items for Drawer
            $criticalLowStock = Product::where('is_active', true)
                ->where(function ($q) {
                    $q->whereColumn('stock', '<=', 'low_stock_threshold')
                      ->orWhereHas('variants', function ($qv) {
                          $qv->whereColumn('stock', '<=', 'low_stock_threshold');
                      });
                })
                ->select('id', 'name', 'name_bn', 'category', 'category_bn', 'stock', 'low_stock_threshold', 'price', 'image_url')
                ->orderBy('stock', 'asc')
                ->limit(8)
                ->get()
                ->toArray();

            return [
                'sales' => [
                    'total_revenue' => $totalRevenue,
                    'total_orders' => $totalOrders,
                    'this_month_revenue' => $thisMonthRevenue,
                    'this_month_orders' => $thisMonthOrders,
                    'last_month_revenue' => $lastMonthRevenue,
                    'last_month_orders' => $lastMonthOrders,
                    'revenue_delta_percent' => $revenueDeltaPercent,
                    'orders_delta_percent' => $ordersDeltaPercent,
                    'avg_order_value' => $avgOrderValue,
                    'status_counts' => $statusCounts,
                    'selected_range' => $range,
                    'range_revenue' => $rangeRevenue,
                    'range_orders' => $rangeOrders,
                ],
                'sales_trend' => $salesTrend,
                'alerts' => [
                    'urgent_service_requests' => $urgentServiceRequests,
                    'pending_orders' => $pendingOrders,
                    'low_stock_count' => $lowStockCount,
                    'pending_reschedule_requests' => $pendingRescheduleRequests,
                    'overdue_followups_count' => $overdueFollowUpsCount,
                    'total_urgent_actions' => $urgentServiceRequests + $pendingOrders + $lowStockCount + $pendingRescheduleRequests,
                ],
                'services' => [
                    'open_requests' => $openServiceRequests,
                    'urgent_requests' => $urgentServiceRequests,
                    'completed_this_month' => $completedThisMonth,
                    'avg_turnaround_hours' => $avgTurnaroundHours,
                    'active_practitioners_count' => $activePractitionersCount,
                    'category_breakdown' => $categoryBreakdown,
                ],
                'critical_low_stock' => $criticalLowStock,
            ];
        });

        return $this->successResponse($summaryData, 'Dashboard summary retrieved successfully');
    }
}
