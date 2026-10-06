<?php

namespace App\Services;

use App\Mail\CriticalSystemAlertMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SystemAlertService
{
    /**
     * Send a critical system alert email to all active admins.
     * Rate-limited to prevent email floods during failure cascades.
     *
     * @param  string  $title  Short title of the critical incident
     * @param  string  $errorMessage  Full error or exception trace summary
     * @param  array<string, mixed>  $context  Incident context metadata
     * @param  int  $cooldownMinutes  Throttling period in minutes for duplicate alerts (default 15)
     */
    public function sendCriticalAlert(
        string $title,
        string $errorMessage,
        array $context = [],
        int $cooldownMinutes = 15
    ): void {
        try {
            $dedupeKey = 'critical_system_alert:'.md5($title.substr($errorMessage, 0, 100));

            // Prevent spamming admins if an identical issue repeats within cooldown window
            if (! Cache::add($dedupeKey, true, now()->addMinutes($cooldownMinutes))) {
                Log::warning("[SYSTEM ALERT THROTTLED] Duplicate critical alert suppressed: {$title}");

                return;
            }

            $admins = User::whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })->whereNotNull('email')->where('is_active', true)->get();

            if ($admins->isEmpty()) {
                Log::error("[CRITICAL ALERT UNROUTED] No active admin with email found to receive alert: {$title}");

                return;
            }

            foreach ($admins as $admin) {
                // We queue the alert mail
                Mail::to($admin->email)->queue(new CriticalSystemAlertMail($title, $errorMessage, $context));
            }

            Log::emergency("[CRITICAL ALERT DISPATCHED] Alert '{$title}' sent to {$admins->count()} admin(s).");
        } catch (\Throwable $e) {
            // Absolute safety: never let an alert dispatch attempt crash the application
            Log::emergency("[CRITICAL ALERT FAILURE] Could not dispatch alert '{$title}': ".$e->getMessage());
        }
    }
}
