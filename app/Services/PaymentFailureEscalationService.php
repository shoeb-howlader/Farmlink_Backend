<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\User;

class PaymentFailureEscalationService
{
    /**
     * Check if a farmer has reached 3 or more failed payment attempts in 24 hours.
     * If so, create exactly one admin notification flagging the farmer for support outreach.
     */
    public function checkAndEscalate(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        $window = now()->subHours(24);

        $failedCount = Order::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('status', 'payment_failed')
                    ->orWhere('payment_status', 'failed');
            })
            ->where('created_at', '>=', $window)
            ->count();

        if ($failedCount >= 3) {
            // Check if an escalation notification was already created in the 24h window
            $alreadyNotified = AdminNotification::where('type', 'payment.failure_escalation')
                ->where('created_at', '>=', $window)
                ->where(function ($q) use ($userId) {
                    $q->where('data->user_id', $userId)
                        ->orWhere('data->user_id', (string) $userId);
                })
                ->exists();

            if (! $alreadyNotified) {
                $user = User::find($userId);
                $name = $user ? $user->name : "Farmer #{$userId}";

                AdminNotification::notify(
                    'payment.failure_escalation',
                    "Multiple Payment Failures: {$name}",
                    "Farmer {$name} has had {$failedCount} failed payment attempts in the last 24 hours. Consider reaching out for support.",
                    [
                        'user_id' => $userId,
                        'failed_count' => $failedCount,
                        'window_hours' => 24,
                    ]
                );

                return true;
            }
        }

        return false;
    }
}
