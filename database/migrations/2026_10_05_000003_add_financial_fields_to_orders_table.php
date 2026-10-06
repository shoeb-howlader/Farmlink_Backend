<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)->nullable()->after('status');
            $table->decimal('delivery_fee', 10, 2)->default(0.00)->after('subtotal');
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('delivery_fee');
            $table->foreignId('coupon_id')->nullable()->after('discount_amount')->constrained('coupons')->nullOnDelete();
        });

        // Backfill existing orders: subtotal = total, delivery_fee = 0.00, discount_amount = 0.00
        DB::table('orders')->whereNull('subtotal')->update([
            'subtotal' => DB::raw('total'),
            'delivery_fee' => 0.00,
            'discount_amount' => 0.00,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn([
                'subtotal',
                'delivery_fee',
                'discount_amount',
                'coupon_id',
            ]);
        });
    }
};
