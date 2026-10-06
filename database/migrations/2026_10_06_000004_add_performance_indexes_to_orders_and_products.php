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
            $table->index(['status', 'created_at'], 'orders_status_created_at_idx');
            $table->index(['user_id', 'created_at'], 'orders_user_id_created_at_idx');
            $table->index('invoice_number', 'orders_invoice_number_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'is_featured'], 'products_is_active_is_featured_idx');
            $table->index(['is_active', 'category'], 'products_is_active_category_idx');
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'farms_user_id_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_created_at_idx');
            $table->dropIndex('orders_user_id_created_at_idx');
            $table->dropIndex('orders_invoice_number_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_is_active_is_featured_idx');
            $table->dropIndex('products_is_active_category_idx');
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->dropIndex('farms_user_id_created_at_idx');
        });
    }
};
