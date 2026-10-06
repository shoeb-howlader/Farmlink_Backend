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
        $tables = ['divisions', 'districts', 'upazilas', 'unions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'bn_name')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('bn_name')->nullable()->after('name');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['unions', 'upazilas', 'districts', 'divisions'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'bn_name')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('bn_name');
                });
            }
        }
    }
};
