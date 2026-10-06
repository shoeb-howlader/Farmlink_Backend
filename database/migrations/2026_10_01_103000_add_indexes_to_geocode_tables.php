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
        Schema::table('districts', function (Blueprint $table) {
            $table->index('division_id', 'districts_division_id_index');
            $table->index('name', 'districts_name_index');
        });

        Schema::table('upazilas', function (Blueprint $table) {
            $table->index('district_id', 'upazilas_district_id_index');
            $table->index('name', 'upazilas_name_index');
        });

        Schema::table('unions', function (Blueprint $table) {
            $table->index('upazila_id', 'unions_upazila_id_index');
            $table->index('name', 'unions_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unions', function (Blueprint $table) {
            $table->dropIndex('unions_upazila_id_index');
            $table->dropIndex('unions_name_index');
        });

        Schema::table('upazilas', function (Blueprint $table) {
            $table->dropIndex('upazilas_district_id_index');
            $table->dropIndex('upazilas_name_index');
        });

        Schema::table('districts', function (Blueprint $table) {
            $table->dropIndex('districts_division_id_index');
            $table->dropIndex('districts_name_index');
        });
    }
};
