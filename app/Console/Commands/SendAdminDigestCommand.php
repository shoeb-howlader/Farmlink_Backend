<?php

namespace App\Console\Commands;

use App\Mail\AdminDigestMail;
use App\Models\ConsultantRecord;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAdminDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmlink:send-admin-digest 
                            {--frequency=daily : Digest frequency (daily or weekly)}
                            {--admin-id= : Send to a specific admin ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch daily or weekly operational digests to administrative staff.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $frequency = strtolower($this->option('frequency') ?: 'daily');
        if (! in_array($frequency, ['daily', 'weekly'])) {
            $this->error("Invalid frequency '{$frequency}'. Allowed: daily, weekly");

            return 1;
        }

        $startDate = $frequency === 'weekly' ? Carbon::now()->subWeek() : Carbon::now()->subDay();
        $this->info("Gathering {$frequency} metrics since {$startDate->toDateTimeString()}...");

        // 1. Sales & Orders in period
        $ordersQuery = Order::where('created_at', '>=', $startDate)
            ->whereNotIn('status', ['cancelled', 'rejected', 'returned']);

        $ordersCount = $ordersQuery->count();
        $salesTotal = (float) $ordersQuery->sum('total');

        // 2. Low stock items
        $lowStockQuery = Product::where('is_active', true)
            ->where(function ($q) {
                $q->where('stock', '<=', 5)
                    ->orWhereRaw('stock <= COALESCE(low_stock_threshold, 5)');
            })
            ->select('id', 'name', 'stock', 'price');

        $lowStockCount = $lowStockQuery->count();
        $lowStockItems = $lowStockQuery->limit(10)->get()->toArray();

        // 3. Pending service requests
        $pendingRequestsCount = ServiceRequest::where('status', 'pending')->count();

        // 4. Overdue follow-ups count
        $overdueVet = VetRecord::whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<', Carbon::today())
            ->whereNull('follow_up_service_request_id')
            ->count();

        $overdueConsultant = ConsultantRecord::whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<', Carbon::today())
            ->whereNull('follow_up_service_request_id')
            ->count();

        $overdueFollowUpsCount = $overdueVet + $overdueConsultant;

        $stats = [
            'ordersCount' => $ordersCount,
            'salesTotal' => $salesTotal,
            'lowStockCount' => $lowStockCount,
            'lowStockItems' => $lowStockItems,
            'pendingRequestsCount' => $pendingRequestsCount,
            'overdueFollowUpsCount' => $overdueFollowUpsCount,
        ];

        // 5. Query recipient administrators
        $adminsQuery = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->whereNotNull('email')->where('is_active', true);

        if ($adminId = $this->option('admin-id')) {
            $adminsQuery->where('id', $adminId);
        }

        $admins = $adminsQuery->get();

        if ($admins->isEmpty()) {
            $this->warn('No active administrators found with email addresses configured.');

            return 0;
        }

        $this->info("Dispatching {$frequency} digest to {$admins->count()} administrator(s)...");

        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(new AdminDigestMail($admin, $stats, $frequency));
            Log::info("[ADMIN DIGEST] Queued {$frequency} digest for admin {$admin->email} (ID: {$admin->id})");
        }

        $this->info("Successfully queued {$admins->count()} digest email(s).");

        return 0;
    }
}
