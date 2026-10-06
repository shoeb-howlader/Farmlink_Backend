<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ip_city', 100)->nullable()->after('ip_country');
            $table->string('ip_region', 100)->nullable()->after('ip_city');
            $table->string('ip_isp', 150)->nullable()->after('ip_region');
            $table->decimal('advance_delivery_fee', 10, 2)->default(0.00)->after('delivery_fee');
            $table->timestamp('advance_paid_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ip_city',
                'ip_region',
                'ip_isp',
                'advance_delivery_fee',
                'advance_paid_at',
            ]);
        });
    }
};
