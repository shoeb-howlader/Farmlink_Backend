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
        DB::table('payment_methods')->where('code', 'farmer_credit')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('payment_methods')->insert([
            'code' => 'farmer_credit',
            'name' => 'Farmer Credit Account',
            'description' => 'Pay via approved credit ledger arrangement',
            'is_active' => true,
            'sort_order' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
