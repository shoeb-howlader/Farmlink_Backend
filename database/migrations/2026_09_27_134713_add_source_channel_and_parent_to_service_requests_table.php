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
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('source_channel')->default('self_service');
            $table->string('parent_record_type')->nullable();
            $table->unsignedBigInteger('parent_record_id')->nullable();

            $table->index('source_channel');
            $table->index(['parent_record_type', 'parent_record_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex(['source_channel']);
            $table->dropIndex(['parent_record_type', 'parent_record_id']);
            $table->dropColumn(['source_channel', 'parent_record_type', 'parent_record_id']);
        });
    }
};
