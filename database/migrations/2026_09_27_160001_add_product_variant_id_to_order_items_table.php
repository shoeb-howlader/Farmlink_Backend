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
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
        });

        // Backfill product_variant_id for existing order items
        $orderItems = DB::table('order_items')->get();
        foreach ($orderItems as $item) {
            $defaultVariant = DB::table('product_variants')
                ->where('product_id', $item->product_id)
                ->where('is_default', true)
                ->first();

            if (! $defaultVariant) {
                $defaultVariant = DB::table('product_variants')
                    ->where('product_id', $item->product_id)
                    ->first();
            }

            if ($defaultVariant) {
                DB::table('order_items')
                    ->where('id', $item->id)
                    ->update(['product_variant_id' => $defaultVariant->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
    }
};
