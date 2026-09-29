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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('variant_label'); // e.g., "250g", "1kg", "25kg", "Standard"
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'is_default']);
        });

        // Migrate existing flat products into a single default variant each
        $products = DB::table('products')->get();
        foreach ($products as $product) {
            $label = 'Standard';
            if (preg_match('/(\d+(?:\.\d+)?\s*(?:kg|g|L|ml|HP))/i', $product->name, $matches)) {
                $label = $matches[1];
            }

            DB::table('product_variants')->insert([
                'product_id' => $product->id,
                'variant_label' => $label,
                'sku' => 'SKU-' . str_pad($product->id, 4, '0', STR_PAD_LEFT) . '-STD',
                'price' => $product->price,
                'stock' => $product->stock,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
