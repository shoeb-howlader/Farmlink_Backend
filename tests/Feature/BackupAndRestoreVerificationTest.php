<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use App\Mail\CriticalSystemAlertMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupAndRestoreVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_configuration_is_valid_and_targets_active_db_and_public_storage(): void
    {
        $backupConfig = config('backup.backup');

        $this->assertEquals(config('app.name', 'Farmlink'), $backupConfig['name']);
        $this->assertContains(storage_path('app/public'), $backupConfig['source']['files']['include']);
        $this->assertContains(config('database.default'), $backupConfig['source']['databases']);
        $this->assertContains(config('backup.backup.destination.disks.0'), ['backup', 's3', 'local']);
    }

    public function test_backup_retention_strategy_matches_production_policy(): void
    {
        $strategy = config('backup.cleanup.default_strategy');

        $this->assertEquals(7, $strategy['keep_all_backups_for_days']);
        $this->assertEquals(7, $strategy['keep_daily_backups_for_days']);
        $this->assertEquals(4, $strategy['keep_weekly_backups_for_weeks']);
        $this->assertEquals(3, $strategy['keep_monthly_backups_for_months']);
    }

    public function test_backup_failure_event_triggers_critical_system_alert(): void
    {
        Mail::fake();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = \App\Models\User::factory()->create([
            'email' => 'admin_backup_test@farmlink.com',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $event = new BackupHasFailed(
            new \Exception('Test S3 storage connection timeout'),
            's3',
            'farmlink-backup-test'
        );

        event($event);

        Mail::assertQueued(CriticalSystemAlertMail::class, function (CriticalSystemAlertMail $mail) {
            return str_contains($mail->title, 'Database Backup Failed');
        });
    }

    public function test_restored_scratch_database_row_counts_match_active_database(): void
    {
        $password = env('DB_PASSWORD', 'Shoeb@123456');
        $username = env('DB_USERNAME', 'postgres');
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '5432');

        config([
            'database.connections.active_pgsql' => [
                'driver' => 'pgsql',
                'host' => $host,
                'port' => $port,
                'database' => 'farmlink',
                'username' => $username,
                'password' => $password,
                'search_path' => 'public',
            ],
            'database.connections.scratch_pgsql' => [
                'driver' => 'pgsql',
                'host' => $host,
                'port' => $port,
                'database' => 'farmlink_scratch_restore',
                'username' => $username,
                'password' => $password,
                'search_path' => 'public',
            ]
        ]);

        $activeUsers = DB::connection('active_pgsql')->table('users')->count();
        $activeOrders = DB::connection('active_pgsql')->table('orders')->count();
        $activePayments = DB::connection('active_pgsql')->table('payments')->count();
        $activeLedgers = DB::connection('active_pgsql')->table('farm_ledger_entries')->count();

        $restoredUsers = DB::connection('scratch_pgsql')->table('users')->count();
        $restoredOrders = DB::connection('scratch_pgsql')->table('orders')->count();
        $restoredPayments = DB::connection('scratch_pgsql')->table('payments')->count();
        $restoredLedgers = DB::connection('scratch_pgsql')->table('farm_ledger_entries')->count();

        $this->assertEquals($activeUsers, $restoredUsers, "User count: {$activeUsers} vs {$restoredUsers}");
        $this->assertEquals($activeOrders, $restoredOrders, "Order count: {$activeOrders} vs {$restoredOrders}");
        $this->assertEquals($activePayments, $restoredPayments, "Payment count: {$activePayments} vs {$restoredPayments}");
        $this->assertEquals($activeLedgers, $restoredLedgers, "Farm ledger count: {$activeLedgers} vs {$restoredLedgers}");
    }
}
