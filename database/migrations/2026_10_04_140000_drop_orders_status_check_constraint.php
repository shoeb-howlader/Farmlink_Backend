<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to re-add restrictive constraint
    }
};
