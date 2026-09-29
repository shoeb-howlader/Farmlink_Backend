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
        Schema::create('visit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vet_record_id')->nullable()->constrained('vet_records')->cascadeOnDelete();
            $table->foreignId('consultant_record_id')->nullable()->constrained('consultant_records')->cascadeOnDelete();
            $table->string('photo_path', 500);
            $table->string('caption', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('vet_record_id');
            $table->index('consultant_record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_photos');
    }
};
