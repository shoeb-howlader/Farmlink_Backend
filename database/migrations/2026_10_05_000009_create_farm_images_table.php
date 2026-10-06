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
        Schema::create('farm_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->string('image_path', 500);
            $table->string('image_thumbnail_path', 500)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('caption', 255)->nullable();
            $table->string('category', 100)->nullable()->default('general');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['farm_id', 'sort_order']);
            $table->index(['farm_id', 'is_primary']);
        });

        // Backfill existing farm photos
        $farms = DB::table('farms')->whereNotNull('image_path')->get();
        foreach ($farms as $farm) {
            DB::table('farm_images')->insert([
                'farm_id' => $farm->id,
                'image_path' => $farm->image_path,
                'image_thumbnail_path' => $farm->image_thumbnail_path ?? null,
                'image_url' => $farm->image_url ?? null,
                'caption' => 'Main Farm Photo',
                'category' => 'general',
                'sort_order' => 0,
                'is_primary' => true,
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
        Schema::dropIfExists('farm_images');
    }
};
