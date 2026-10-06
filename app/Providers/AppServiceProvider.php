<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\FarmLedgerEntry::class,
            \App\Policies\FarmLedgerEntryPolicy::class
        );

        // Dispatches critical alert email to admins on unhandled queue job failure
        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            $jobName = $event->job->resolveName();

            // Prevent recursion if the critical alert mail itself fails
            if (str_contains($jobName, 'CriticalSystemAlertMail')) {
                return;
            }

            $title = "Failed Queue Job: {$jobName}";
            $errorMessage = $event->exception->getMessage();
            $context = [
                'job_name' => $jobName,
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'failed_at' => now()->toDateTimeString(),
                'attempts' => $event->job->attempts(),
            ];

            app(\App\Services\SystemAlertService::class)->sendCriticalAlert($title, $errorMessage, $context);
        });
    }
}

