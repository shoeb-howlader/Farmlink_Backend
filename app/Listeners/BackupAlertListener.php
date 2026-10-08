<?php

namespace App\Listeners;

use App\Services\SystemAlertService;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class BackupAlertListener
{
    public function __construct(
        protected SystemAlertService $systemAlertService
    ) {}

    public function handleBackupFailed(BackupHasFailed $event): void
    {
        $this->systemAlertService->sendCriticalAlert(
            'Database Backup Failed',
            $event->exception->getMessage(),
            [
                'disk' => $event->diskName,
                'backup_name' => $event->backupName,
                'file' => $event->exception->getFile(),
                'line' => $event->exception->getLine(),
            ]
        );
    }

    public function handleUnhealthyBackup(UnhealthyBackupWasFound $event): void
    {
        $messages = collect($event->failureMessages)
            ->map(fn ($item) => is_array($item) ? ($item['message'] ?? json_encode($item)) : (string) $item)
            ->implode('; ');

        $this->systemAlertService->sendCriticalAlert(
            'Unhealthy Backup Detected',
            $messages ?: 'Backup health check failed',
            [
                'disk' => $event->diskName,
                'backup_name' => $event->backupName,
            ]
        );
    }

    public function handleCleanupFailed(CleanupHasFailed $event): void
    {
        $this->systemAlertService->sendCriticalAlert(
            'Backup Cleanup Failed',
            $event->exception->getMessage(),
            [
                'disk' => $event->backupDestination?->diskName(),
            ]
        );
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            BackupHasFailed::class => 'handleBackupFailed',
            UnhealthyBackupWasFound::class => 'handleUnhealthyBackup',
            CleanupHasFailed::class => 'handleCleanupFailed',
        ];
    }
}
