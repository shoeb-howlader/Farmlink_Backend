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
        // 1. Telemetry time-series aggregation index for global district heatmap
        if (Schema::hasTable('pond_telemetries')) {
            Schema::table('pond_telemetries', function (Blueprint $table) {
                $table->index('recorded_at', 'pond_telemetries_recorded_at_idx');
            });
        }

        // 2. Farm geographical district/upazila composite index for location grouping
        if (Schema::hasTable('farms')) {
            Schema::table('farms', function (Blueprint $table) {
                $table->index(['district', 'upazila'], 'farms_district_upazila_idx');
            });
        }

        // 3. Products storefront sort index (ORDER BY created_at DESC)
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['is_active', 'created_at'], 'products_active_created_idx');
            });
        }

        // 4. Product Reviews rating aggregate index for withAvg / withCount
        if (Schema::hasTable('product_reviews')) {
            Schema::table('product_reviews', function (Blueprint $table) {
                $table->index(['product_id', 'rating'], 'product_reviews_product_rating_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pond_telemetries')) {
            Schema::table('pond_telemetries', function (Blueprint $table) {
                $table->dropIndex('pond_telemetries_recorded_at_idx');
            });
        }

        if (Schema::hasTable('farms')) {
            Schema::table('farms', function (Blueprint $table) {
                $table->dropIndex('farms_district_upazila_idx');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_active_created_idx');
            });
        }

        if (Schema::hasTable('product_reviews')) {
            Schema::table('product_reviews', function (Blueprint $table) {
                $table->dropIndex('product_reviews_product_rating_idx');
            });
        }
    }
};
