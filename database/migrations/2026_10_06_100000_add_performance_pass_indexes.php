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
            $table->index('farm_id', 'orders_farm_id_idx');
            $table->index('created_at', 'orders_created_at_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id', 'order_items_order_id_idx');
            $table->index('product_id', 'order_items_product_id_idx');
            $table->index('product_variant_id', 'order_items_product_variant_id_idx');
        });

        Schema::table('vet_records', function (Blueprint $table) {
            $table->index('farm_id', 'vet_records_farm_id_idx');
            $table->index('vet_id', 'vet_records_vet_id_idx');
            $table->index('next_follow_up', 'vet_records_next_follow_up_idx');
            $table->index('visit_date', 'vet_records_visit_date_idx');
        });

        Schema::table('consultant_records', function (Blueprint $table) {
            $table->index('farm_id', 'consultant_records_farm_id_idx');
            $table->index('consultant_id', 'consultant_records_consultant_id_idx');
            $table->index('next_follow_up', 'consultant_records_next_follow_up_idx');
            $table->index('visit_date', 'consultant_records_visit_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('order_id', 'payments_order_id_idx');
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->index('district_id', 'farms_district_id_idx');
            $table->index('district', 'farms_district_idx');
        });

        Schema::table('farm_ledger_entries', function (Blueprint $table) {
            $table->index(['farm_id', 'entry_date'], 'farm_ledger_entries_farm_date_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('stock', 'products_stock_idx');
            $table->index(['is_active', 'stock'], 'products_is_active_stock_idx');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('stock', 'product_variants_stock_idx');
            $table->index(['product_id', 'stock'], 'product_variants_product_stock_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_farm_id_idx');
            $table->dropIndex('orders_created_at_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_id_idx');
            $table->dropIndex('order_items_product_id_idx');
            $table->dropIndex('order_items_product_variant_id_idx');
        });

        Schema::table('vet_records', function (Blueprint $table) {
            $table->dropIndex('vet_records_farm_id_idx');
            $table->dropIndex('vet_records_vet_id_idx');
            $table->dropIndex('vet_records_next_follow_up_idx');
            $table->dropIndex('vet_records_visit_date_idx');
        });

        Schema::table('consultant_records', function (Blueprint $table) {
            $table->dropIndex('consultant_records_farm_id_idx');
            $table->dropIndex('consultant_records_consultant_id_idx');
            $table->dropIndex('consultant_records_next_follow_up_idx');
            $table->dropIndex('consultant_records_visit_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_order_id_idx');
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->dropIndex('farms_district_id_idx');
            $table->dropIndex('farms_district_idx');
        });

        Schema::table('farm_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('farm_ledger_entries_farm_date_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_stock_idx');
            $table->dropIndex('products_is_active_stock_idx');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_stock_idx');
            $table->dropIndex('product_variants_product_stock_idx');
        });
    }
};
