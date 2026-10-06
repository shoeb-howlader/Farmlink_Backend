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
        Schema::create('pourashavas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upazila_id')->constrained('upazilas')->cascadeOnDelete();
            $table->string('name');
            $table->string('bn_name')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();

            $table->index('upazila_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pourashavas');
    }
};
