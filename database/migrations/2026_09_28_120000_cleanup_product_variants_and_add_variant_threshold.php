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
        // 1. Deduplicate known and any generic duplicates in product_variants
        // AquaClean: 1L Standard (id 27) vs 1L (id 1)
        $this->mergeVariants(44, '1L Standard', '1L');

        // Bio-Aqua: 500g Bottle (id 33) vs 500g (id 9)
        $this->mergeVariants(34, '500g Bottle', '500g');

        // Mega Aqua Feed Grower: 25kg Commercial (id 31) vs 25kg (id 3)
        $this->mergeVariants(1, '25kg Commercial', '25kg');

        // Generic deduplication if any other product has duplicate (product_id, variant_label)
        $duplicates = DB::table('product_variants')
            ->select('product_id', 'variant_label', DB::raw('COUNT(*) as count'), DB::raw('MIN(id) as keep_id'))
            ->groupBy('product_id', 'variant_label')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $otherIds = DB::table('product_variants')
                ->where('product_id', $dup->product_id)
                ->where('variant_label', $dup->variant_label)
                ->where('id', '!=', $dup->keep_id)
                ->pluck('id');

            foreach ($otherIds as $oid) {
                DB::table('order_items')->where('product_variant_id', $oid)->update(['product_variant_id' => $dup->keep_id]);
                DB::table('product_variants')->where('id', $oid)->delete();
            }
        }

        // 2. Add unique constraint on (product_id, variant_label)
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['product_id', 'variant_label']);
            if (! Schema::hasColumn('product_variants', 'low_stock_threshold')) {
                $table->unsignedInteger('low_stock_threshold')->default(10)->after('stock');
            }
        });

        // 3. Add product_variant_id to product_stock_adjustments if not exists
        if (Schema::hasTable('product_stock_adjustments') && ! Schema::hasColumn('product_stock_adjustments', 'product_variant_id')) {
            Schema::table('product_stock_adjustments', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            });
        }

        // 4. Clean parent product names for multi-variant products (remove pack size from parent name)
        $multiVariantProductIds = DB::table('product_variants')
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('product_id');

        foreach ($multiVariantProductIds as $pid) {
            $product = DB::table('products')->where('id', $pid)->first();
            if ($product) {
                $cleanedName = preg_replace('/\s+\d+(\.\d+)?\s*(kg|g|L|ml|HP)$/i', '', $product->name);
                if ($cleanedName && $cleanedName !== $product->name) {
                    DB::table('products')->where('id', $pid)->update(['name' => trim($cleanedName)]);
                }
            }
        }
    }

    /**
     * Merge a duplicate variant into a target variant.
     */
    private function mergeVariants(int $productId, string $duplicateLabel, string $targetLabel): void
    {
        $dup = DB::table('product_variants')
            ->where('product_id', $productId)
            ->where('variant_label', $duplicateLabel)
            ->first();

        $target = DB::table('product_variants')
            ->where('product_id', $productId)
            ->where('variant_label', $targetLabel)
            ->first();

        if ($dup && $target) {
            // Reassign any order items
            DB::table('order_items')->where('product_variant_id', $dup->id)->update([
                'product_variant_id' => $target->id,
            ]);

            // Delete duplicate variant
            DB::table('product_variants')->where('id', $dup->id)->delete();
        } elseif ($dup && ! $target) {
            // Just rename the label to standard
            DB::table('product_variants')->where('id', $dup->id)->update([
                'variant_label' => $targetLabel,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'variant_label']);
            $table->dropColumn('low_stock_threshold');
        });

        if (Schema::hasTable('product_stock_adjustments') && Schema::hasColumn('product_stock_adjustments', 'product_variant_id')) {
            Schema::table('product_stock_adjustments', function (Blueprint $table) {
                $table->dropForeign(['product_variant_id']);
                $table->dropColumn('product_variant_id');
            });
        }
    }
};
