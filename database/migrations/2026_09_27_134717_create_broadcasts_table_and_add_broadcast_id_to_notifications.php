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
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('target_audience'); // all_farmers, district_farmers, all_staff, staff_role, manual
            $table->string('target_district')->nullable();
            $table->string('target_role')->nullable();
            $table->json('target_user_ids')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('target_audience');
            $table->index('created_by');
        });

        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->foreignId('broadcast_id')->nullable()->constrained('broadcasts')->cascadeOnDelete();
            $table->index('broadcast_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropForeign(['broadcast_id']);
            $table->dropColumn('broadcast_id');
        });

        Schema::dropIfExists('broadcasts');
    }
};
