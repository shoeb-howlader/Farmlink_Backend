<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add short_description, usage_instructions, video_url to products
        Schema::table('products', function (Blueprint $table) {
            $table->string('short_description', 255)->nullable()->after('category');
            $table->text('usage_instructions')->nullable()->after('description');
            $table->string('video_url', 500)->nullable()->after('usage_instructions');
        });

        // 2. Add alt_text to product_images
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('alt_text', 255)->nullable()->after('image_path');
        });

        // 3. Create product_specs table
        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('label', 100);
            $table->text('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        // 4. Create product_documents table
        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('file_path', 500);
            $table->string('type', 30)->default('datasheet'); // datasheet, safety, certificate
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'type']);
        });

        // 5. Data Migration: Backfill short_description from existing description
        $products = DB::table('products')->get();
        foreach ($products as $product) {
            $desc = $product->description ? trim(strip_tags($product->description)) : '';
            $short = Str::limit($desc, 155, '...');

            DB::table('products')->where('id', $product->id)->update([
                'short_description' => $short ?: 'Verified aquaculture input certified for commercial pond farming.',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_documents');
        Schema::dropIfExists('product_specs');

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('alt_text');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['short_description', 'usage_instructions', 'video_url']);
        });
    }
};
