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
        // 1. Create structured visit test results table
        Schema::create('visit_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vet_record_id')->nullable()->constrained('vet_records')->cascadeOnDelete();
            $table->foreignId('consultant_record_id')->nullable()->constrained('consultant_records')->cascadeOnDelete();
            $table->string('parameter');
            $table->string('value');
            $table->string('unit')->nullable();
            $table->string('reference_range')->nullable();
            $table->string('flag')->nullable(); // normal, high, low, abnormal
            $table->timestamps();

            $table->index('vet_record_id');
            $table->index('consultant_record_id');
        });

        // 2. Extend prescription_items to support consultant recommendations
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->string('type')->default('medicine')->nullable()->after('prescription_id'); // medicine, feed, equipment, practice_change, other
            $table->text('reasoning')->nullable()->after('instructions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn(['type', 'reasoning']);
        });

        Schema::dropIfExists('visit_test_results');
    }
};
