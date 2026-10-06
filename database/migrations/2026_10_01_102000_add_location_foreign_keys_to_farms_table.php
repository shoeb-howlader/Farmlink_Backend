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
        Schema::table('farms', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('cultivation_area')->constrained('divisions')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('division_id')->constrained('districts')->nullOnDelete();
            $table->foreignId('upazila_id')->nullable()->after('district_id')->constrained('upazilas')->nullOnDelete();
            $table->foreignId('union_id')->nullable()->after('upazila_id')->constrained('unions')->nullOnDelete();
            $table->foreignId('pourashava_id')->nullable()->after('union_id')->constrained('pourashavas')->nullOnDelete();

            $table->index(['district_id', 'upazila_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
            $table->dropForeign(['district_id']);
            $table->dropForeign(['upazila_id']);
            $table->dropForeign(['union_id']);
            $table->dropForeign(['pourashava_id']);

            $table->dropColumn([
                'division_id',
                'district_id',
                'upazila_id',
                'union_id',
                'pourashava_id',
            ]);
        });
    }
};
