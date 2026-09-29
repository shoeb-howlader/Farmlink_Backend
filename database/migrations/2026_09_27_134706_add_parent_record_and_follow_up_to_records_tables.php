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
        Schema::table('vet_records', function (Blueprint $table) {
            $table->foreignId('parent_record_id')->nullable()->constrained('vet_records')->nullOnDelete();
            $table->foreignId('follow_up_service_request_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $table->timestamp('lead_reminder_sent_at')->nullable();
            $table->timestamp('overdue_reminder_sent_at')->nullable();
        });

        Schema::table('consultant_records', function (Blueprint $table) {
            $table->foreignId('parent_record_id')->nullable()->constrained('consultant_records')->nullOnDelete();
            $table->foreignId('follow_up_service_request_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $table->timestamp('lead_reminder_sent_at')->nullable();
            $table->timestamp('overdue_reminder_sent_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultant_records', function (Blueprint $table) {
            $table->dropForeign(['parent_record_id']);
            $table->dropForeign(['follow_up_service_request_id']);
            $table->dropColumn(['parent_record_id', 'follow_up_service_request_id', 'lead_reminder_sent_at', 'overdue_reminder_sent_at']);
        });

        Schema::table('vet_records', function (Blueprint $table) {
            $table->dropForeign(['parent_record_id']);
            $table->dropForeign(['follow_up_service_request_id']);
            $table->dropColumn(['parent_record_id', 'follow_up_service_request_id', 'lead_reminder_sent_at', 'overdue_reminder_sent_at']);
        });
    }
};
