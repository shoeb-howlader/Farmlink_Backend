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

        // Production safety guard: Refuse or log critical alert if unsafe configs exist in production
        if (app()->isProduction()) {
            if (config('app.debug')) {
                $msg = 'CRITICAL SECURITY MISCONFIGURATION: Production environment is running with APP_DEBUG=true! Sensitive credentials and traces could be exposed.';
                \Illuminate\Support\Facades\Log::emergency($msg);
                app(\App\Services\SystemAlertService::class)->sendCriticalAlert(
                    'Production Security Hazard: APP_DEBUG is True',
                    $msg,
                    ['app_env' => 'production', 'app_debug' => true]
                );

                if (! app()->runningInConsole() && ! app()->runningUnitTests()) {
                    abort(500, 'Security misconfiguration: debug mode is prohibited in production.');
                }
            }

            if (config('sslcommerz.is_sandbox')) {
                $msg = 'CRITICAL PAYMENT MISCONFIGURATION: Production environment is running with SSLCommerz sandbox mode enabled! Live payments will not settle.';
                \Illuminate\Support\Facades\Log::emergency($msg);
                app(\App\Services\SystemAlertService::class)->sendCriticalAlert(
                    'Production Payment Hazard: SSLCommerz Sandbox Active',
                    $msg,
                    ['app_env' => 'production', 'is_sandbox' => true]
                );
            }
        }

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

            if (app()->bound('sentry')) {
                \Sentry\captureException($event->exception);
            }

            app(\App\Services\SystemAlertService::class)->sendCriticalAlert($title, $errorMessage, $context);
        });

        // Capture scheduled task failures in Sentry and alert admins
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\ScheduledTaskFailed::class,
            function (\Illuminate\Console\Events\ScheduledTaskFailed $event) {
                if (app()->bound('sentry')) {
                    \Sentry\captureException($event->exception);
                }

                $command = $event->task->command ?? 'Unknown schedule task';
                $title = "Failed Scheduled Command: {$command}";
                $errorMessage = $event->exception->getMessage();
                $context = [
                    'command' => $command,
                    'expression' => $event->task->expression,
                    'failed_at' => now()->toDateTimeString(),
                ];

                app(\App\Services\SystemAlertService::class)->sendCriticalAlert($title, $errorMessage, $context);
            }
        );

        // Capture failed outgoing mail delivery
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Mail\Events\MessageSendingFailed::class,
            function (\Illuminate\Mail\Events\MessageSendingFailed $event) {
                if (app()->bound('sentry')) {
                    \Sentry\captureException($event->exception);
                }

                \Illuminate\Support\Facades\Log::error('[MAIL DELIVERY FAILED] ' . $event->exception->getMessage(), [
                    'recipients' => array_keys($event->message->getTo() ?? []),
                ]);
            }
        );

        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\BackupAlertListener::class);
    }
}

