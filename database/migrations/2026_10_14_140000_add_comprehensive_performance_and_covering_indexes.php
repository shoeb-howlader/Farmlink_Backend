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
        // 1. Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index(['is_active', 'status'], 'users_is_active_status_idx');
            $table->index('district', 'users_district_idx');
            $table->index(['status', 'created_at'], 'users_status_created_at_idx');
            $table->index('is_blacklisted', 'users_is_blacklisted_idx');
            $table->index('cod_blocked', 'users_cod_blocked_idx');
            $table->index('consultations_blocked', 'users_consultations_blocked_idx');
            $table->index('created_at', 'users_created_at_idx');
        });

        // 2. Orders table indexes
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['farm_id', 'created_at'], 'orders_farm_id_created_at_idx');
            $table->index(['user_id', 'status', 'created_at'], 'orders_user_status_created_idx');
            $table->index('is_flagged', 'orders_is_flagged_idx');
            $table->index('risk_level', 'orders_risk_level_idx');
            $table->index('verification_status', 'orders_verification_status_idx');
        });

        // 3. Service Requests table indexes
        Schema::table('service_requests', function (Blueprint $table) {
            $table->index(['status', 'completed_at'], 'sr_status_completed_at_idx');
            $table->index(['farmer_id', 'status'], 'sr_farmer_id_status_idx');
            $table->index(['assigned_to', 'status'], 'sr_assigned_to_status_idx');
            $table->index(['status', 'created_at'], 'sr_status_created_at_idx');
        });

        // 4. Farms table indexes
        Schema::table('farms', function (Blueprint $table) {
            $table->index('farm_type', 'farms_farm_type_idx');
            $table->index('main_culture_type', 'farms_main_culture_type_idx');
            $table->index('farming_system', 'farms_farming_system_idx');
            $table->index(['user_id', 'farm_type'], 'farms_user_id_farm_type_idx');
        });

        // 5. Activity Logs table indexes
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('user_id', 'activity_logs_user_id_idx');
            $table->index('created_at', 'activity_logs_created_at_idx');
            $table->index(['user_id', 'created_at'], 'activity_logs_user_id_created_at_idx');
        });

        // 6. District Delivery Rates table index
        Schema::table('district_delivery_rates', function (Blueprint $table) {
            $table->index('district_id', 'district_delivery_rates_district_id_idx');
        });

        // 7. Product Stock Adjustments table indexes
        Schema::table('product_stock_adjustments', function (Blueprint $table) {
            $table->index(['product_id', 'created_at'], 'stock_adjustments_product_created_idx');
            $table->index('user_id', 'stock_adjustments_user_id_idx');
        });

        // 8. Product Variants table indexes
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('sku', 'product_variants_sku_idx');
        });

        // 9. Products table indexes
        Schema::table('products', function (Blueprint $table) {
            $table->index('price', 'products_price_idx');
            $table->index(['is_active', 'price'], 'products_is_active_price_idx');
        });

        // 10. Farm Ledger Entries table indexes
        Schema::table('farm_ledger_entries', function (Blueprint $table) {
            $table->index(['farm_id', 'voided_at', 'entry_date'], 'farm_ledger_farm_void_date_idx');
            $table->index(['farm_id', 'entry_type', 'voided_at'], 'farm_ledger_farm_type_void_idx');
        });

        // 11. Reschedule Requests table indexes
        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->index('farmer_id', 'reschedule_requests_farmer_id_idx');
            $table->index('farm_id', 'reschedule_requests_farm_id_idx');
            $table->index(['practitioner_id', 'status'], 'reschedule_requests_practitioner_status_idx');
        });

        // 12. Coupon Redemptions table index
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->index('order_id', 'coupon_redemptions_order_id_idx');
        });

        // 13. Product Reviews table index
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->index(['product_id', 'created_at'], 'product_reviews_product_created_idx');
        });

        // 14. Taxonomies table index
        Schema::table('taxonomies', function (Blueprint $table) {
            $table->index(['type', 'is_active', 'sort_order'], 'taxonomies_type_active_sort_idx');
        });

        // 15. Vet Records table indexes
        Schema::table('vet_records', function (Blueprint $table) {
            $table->index(['vet_id', 'visit_date'], 'vet_records_vet_visit_date_idx');
            $table->index(['farm_id', 'visit_date'], 'vet_records_farm_visit_date_idx');
        });

        // 16. Consultant Records table indexes
        Schema::table('consultant_records', function (Blueprint $table) {
            $table->index(['consultant_id', 'visit_date'], 'consultant_records_cons_visit_idx');
            $table->index(['farm_id', 'visit_date'], 'consultant_records_farm_visit_idx');
        });

        // 17. Jobs table composite index for worker polling
        if (Schema::hasTable('jobs')) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->index(['queue', 'reserved_at', 'available_at'], 'jobs_queue_reserved_available_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_is_active_status_idx');
            $table->dropIndex('users_district_idx');
            $table->dropIndex('users_status_created_at_idx');
            $table->dropIndex('users_is_blacklisted_idx');
            $table->dropIndex('users_cod_blocked_idx');
            $table->dropIndex('users_consultations_blocked_idx');
            $table->dropIndex('users_created_at_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_farm_id_created_at_idx');
            $table->dropIndex('orders_user_status_created_idx');
            $table->dropIndex('orders_is_flagged_idx');
            $table->dropIndex('orders_risk_level_idx');
            $table->dropIndex('orders_verification_status_idx');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex('sr_status_completed_at_idx');
            $table->dropIndex('sr_farmer_id_status_idx');
            $table->dropIndex('sr_assigned_to_status_idx');
            $table->dropIndex('sr_status_created_at_idx');
        });

        Schema::table('farms', function (Blueprint $table) {
            $table->dropIndex('farms_farm_type_idx');
            $table->dropIndex('farms_main_culture_type_idx');
            $table->dropIndex('farms_farming_system_idx');
            $table->dropIndex('farms_user_id_farm_type_idx');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_user_id_idx');
            $table->dropIndex('activity_logs_created_at_idx');
            $table->dropIndex('activity_logs_user_id_created_at_idx');
        });

        Schema::table('district_delivery_rates', function (Blueprint $table) {
            $table->dropIndex('district_delivery_rates_district_id_idx');
        });

        Schema::table('product_stock_adjustments', function (Blueprint $table) {
            $table->dropIndex('stock_adjustments_product_created_idx');
            $table->dropIndex('stock_adjustments_user_id_idx');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_sku_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_price_idx');
            $table->dropIndex('products_is_active_price_idx');
        });

        Schema::table('farm_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('farm_ledger_farm_void_date_idx');
            $table->dropIndex('farm_ledger_farm_type_void_idx');
        });

        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->dropIndex('reschedule_requests_farmer_id_idx');
            $table->dropIndex('reschedule_requests_farm_id_idx');
            $table->dropIndex('reschedule_requests_practitioner_status_idx');
        });

        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->dropIndex('coupon_redemptions_order_id_idx');
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropIndex('product_reviews_product_created_idx');
        });

        Schema::table('taxonomies', function (Blueprint $table) {
            $table->dropIndex('taxonomies_type_active_sort_idx');
        });

        Schema::table('vet_records', function (Blueprint $table) {
            $table->dropIndex('vet_records_vet_visit_date_idx');
            $table->dropIndex('vet_records_farm_visit_date_idx');
        });

        Schema::table('consultant_records', function (Blueprint $table) {
            $table->dropIndex('consultant_records_cons_visit_idx');
            $table->dropIndex('consultant_records_farm_visit_idx');
        });

        if (Schema::hasTable('jobs')) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->dropIndex('jobs_queue_reserved_available_idx');
            });
        }
    }
};
