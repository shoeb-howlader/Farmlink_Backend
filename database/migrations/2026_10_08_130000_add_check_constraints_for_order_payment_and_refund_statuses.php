<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        $orderStatuses = implode("', '", OrderStatus::values());
        $paymentStatuses = implode("', '", PaymentStatus::values());
        $refundStatuses = implode("', '", RefundStatus::values());

        if ($driver === 'pgsql') {
            // Drop any preexisting status constraints
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check;');
            DB::statement('ALTER TABLE refunds DROP CONSTRAINT IF EXISTS refunds_status_check;');

            // Apply PostgreSQL CHECK constraints sourced directly from PHP Enums
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('{$orderStatuses}'));");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('{$paymentStatuses}'));");
            DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('{$refundStatuses}'));");
        } elseif ($driver === 'sqlite') {
            // Emulate CHECK constraints on SQLite using triggers so SQLite memory test suite enforces them
            $this->createSqliteTrigger('orders', $orderStatuses);
            $this->createSqliteTrigger('payments', $paymentStatuses);
            $this->createSqliteTrigger('refunds', $refundStatuses);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check;');
            DB::statement('ALTER TABLE refunds DROP CONSTRAINT IF EXISTS refunds_status_check;');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS validate_orders_status_insert;');
            DB::statement('DROP TRIGGER IF EXISTS validate_orders_status_update;');
            DB::statement('DROP TRIGGER IF EXISTS validate_payments_status_insert;');
            DB::statement('DROP TRIGGER IF EXISTS validate_payments_status_update;');
            DB::statement('DROP TRIGGER IF EXISTS validate_refunds_status_insert;');
            DB::statement('DROP TRIGGER IF EXISTS validate_refunds_status_update;');
        }
    }

    private function createSqliteTrigger(string $table, string $allowedStatuses): void
    {
        DB::unprepared("
            CREATE TRIGGER IF NOT EXISTS validate_{$table}_status_insert
            BEFORE INSERT ON {$table}
            FOR EACH ROW
            WHEN NEW.status NOT IN ('{$allowedStatuses}')
            BEGIN
                SELECT RAISE(ABORT, 'CHECK constraint failed: {$table}.status');
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER IF NOT EXISTS validate_{$table}_status_update
            BEFORE UPDATE ON {$table}
            FOR EACH ROW
            WHEN NEW.status NOT IN ('{$allowedStatuses}')
            BEGIN
                SELECT RAISE(ABORT, 'CHECK constraint failed: {$table}.status');
            END;
        ");
    }
};
