<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends ApiController
{
    /**
     * Get sales analytics and breakdown.
     */
    public function sales(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $dateFrom = $request->query('from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('to', now()->toDateString());
        $datetimeStart = $dateFrom . ' 00:00:00';
        $datetimeEnd = $dateTo . ' 23:59:59';
        $excludedStatuses = ['cancelled', 'rejected', 'returned'];

        $ordersQuery = Order::whereBetween('created_at', [$datetimeStart, $datetimeEnd]);

        // Breakdown by status (computes all metric sums and counts in 1 aggregated query)
        $statusBreakdown = (clone $ordersQuery)
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total) as total'))
            ->groupBy('status')
            ->get();

        $totalOrders = (int) $statusBreakdown->sum('count');
        $completedOrders = (int) ($statusBreakdown->firstWhere('status', 'delivered')?->count ?? 0);
        $cancelledOrders = (int) $statusBreakdown->whereIn('status', $excludedStatuses)->sum('count');
        $totalRevenue = (float) $statusBreakdown->whereNotIn('status', $excludedStatuses)->sum('total');
        $validOrdersCount = $totalOrders - $cancelledOrders;
        $avgOrderValue = $validOrdersCount > 0 ? round($totalRevenue / $validOrdersCount, 2) : 0;

        // Top products by quantity sold
        $topProducts = OrderItem::select('product_id', DB::raw('sum(quantity) as total_qty'), DB::raw('sum(price_at_purchase * quantity) as total_sales'))
            ->whereHas('order', function ($q) use ($datetimeStart, $datetimeEnd, $excludedStatuses) {
                $q->whereBetween('created_at', [$datetimeStart, $datetimeEnd])
                    ->whereNotIn('status', $excludedStatuses);
            })
            ->with('product:id,name,sku,price')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name ?? 'Unknown Product',
                    'sku' => $item->product?->sku ?? '-',
                    'total_quantity' => (int) $item->total_qty,
                    'total_sales' => (float) $item->total_sales,
                ];
            });

        return $this->successResponse([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'metrics' => [
                'total_revenue' => (float) $totalRevenue,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'avg_order_value' => $avgOrderValue,
            ],
            'status_breakdown' => $statusBreakdown,
            'top_products' => $topProducts,
        ], 'Sales report generated successfully');
    }

    /**
     * Export sales data as CSV.
     */
    public function exportSales(Request $request): StreamedResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $dateFrom = $request->query('from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('to', now()->toDateString());
        $filename = 'sales-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($dateFrom, $dateTo) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order ID', 'Date', 'Customer Name', 'Customer Phone', 'Status', 'Total Amount', 'Items Count']);

            Order::with(['user', 'items'])
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->latest('id')
                ->chunk(100, function ($orders) use ($handle) {
                    foreach ($orders as $order) {
                        fputcsv($handle, [
                            $order->id,
                            $order->created_at?->toDateTimeString(),
                            $order->user?->name ?? 'N/A',
                            $order->user?->phone ?? 'N/A',
                            $order->status,
                            $order->total,
                            $order->items->count(),
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get farmers demographic and farm summary.
     */
    public function farmers(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $farmersQuery = User::role('farmer');

        $totalFarmers = (clone $farmersQuery)->count();
        $activeFarmers = (clone $farmersQuery)->where('is_active', true)->count();
        $totalFarms = Farm::count();
        $totalAnimals = Farm::sum('animals_count');

        $districtBreakdown = (clone $farmersQuery)
            ->select('district', DB::raw('count(*) as count'))
            ->whereNotNull('district')
            ->groupBy('district')
            ->orderByDesc('count')
            ->get();

        return $this->successResponse([
            'metrics' => [
                'total_farmers' => $totalFarmers,
                'active_farmers' => $activeFarmers,
                'total_farms' => $totalFarms,
                'total_animals' => (int) $totalAnimals,
            ],
            'district_breakdown' => $districtBreakdown,
        ], 'Farmers report generated successfully');
    }

    /**
     * Export farmers data as CSV.
     */
    public function exportFarmers(Request $request): StreamedResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $filename = 'farmers-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Farmer ID', 'Name', 'Phone', 'Email', 'District', 'Farms Count', 'Total Animals', 'Status', 'Registered At']);

            User::role('farmer')
                ->with(['farms'])
                ->latest('id')
                ->chunk(100, function ($farmers) use ($handle) {
                    foreach ($farmers as $farmer) {
                        fputcsv($handle, [
                            $farmer->id,
                            $farmer->name,
                            $farmer->phone,
                            $farmer->email ?? '',
                            $farmer->district ?? '',
                            $farmer->farms->count(),
                            $farmer->farms->sum('animals_count'),
                            ($farmer->is_active ?? true) ? 'Active' : 'Inactive',
                            $farmer->created_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get top farmers by spend this month.
     */
    public function topFarmers(Request $request): JsonResponse
    {
        Gate::authorize('viewAdmin', Order::class);

        $now = now();
        $year = $request->integer('year', $now->year);
        $month = $request->integer('month', $now->month);

        $topFarmers = User::role('farmer')
            ->whereHas('orders', function ($q) use ($year, $month) {
                $q->whereMonth('created_at', $month)
                    ->whereYear('created_at', $year)
                    ->whereNotIn('status', ['cancelled', 'rejected', 'returned'])
                    ->where('total', '>', 0);
            })
            ->withSum([
                'orders' => function ($q) use ($year, $month) {
                    $q->whereMonth('created_at', $month)
                        ->whereYear('created_at', $year)
                        ->whereNotIn('status', ['cancelled', 'rejected', 'returned']);
                }
            ], 'total')
            ->withCount([
                'orders' => function ($q) use ($year, $month) {
                    $q->whereMonth('created_at', $month)
                        ->whereYear('created_at', $year)
                        ->whereNotIn('status', ['cancelled', 'rejected', 'returned']);
                }
            ])
            ->orderByDesc('orders_sum_total')
            ->orderByDesc('orders_count')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(function (User $farmer) {
                return [
                    'id' => $farmer->id,
                    'name' => $farmer->name,
                    'phone' => $farmer->phone,
                    'district' => $farmer->district,
                    'avatar_url' => $farmer->avatar_url,
                    'orders_count' => (int) ($farmer->orders_count ?? 0),
                    'total_spent' => (float) ($farmer->orders_sum_total ?? 0),
                ];
            });

        return $this->successResponse($topFarmers, 'Top farmers this month retrieved successfully');
    }
}
