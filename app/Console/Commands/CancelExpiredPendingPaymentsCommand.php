<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CancelExpiredPendingPaymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:cancel-expired {--minutes= : Override timeout minutes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-cancel abandoned pending_payment orders whose payment timeout has elapsed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) ($this->option('minutes') ?: config('sslcommerz.order_timeout_minutes', 45));

        return $this->call('payments:reconcile', [
            '--timeout' => $minutes,
            '--age' => 3,
        ]);
    }
}
