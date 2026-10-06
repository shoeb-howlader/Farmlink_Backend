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
            $table->date('original_follow_up_date')->nullable()->after('next_follow_up');
            $table->text('rescheduled_reason')->nullable()->after('original_follow_up_date');
            $table->timestamp('rescheduled_at')->nullable()->after('rescheduled_reason');
            $table->foreignId('rescheduled_by')->nullable()->after('rescheduled_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('consultant_records', function (Blueprint $table) {
            $table->date('original_follow_up_date')->nullable()->after('next_follow_up');
            $table->text('rescheduled_reason')->nullable()->after('original_follow_up_date');
            $table->timestamp('rescheduled_at')->nullable()->after('rescheduled_reason');
            $table->foreignId('rescheduled_by')->nullable()->after('rescheduled_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vet_records', function (Blueprint $table) {
            $table->dropForeign(['rescheduled_by']);
            $table->dropColumn(['original_follow_up_date', 'rescheduled_reason', 'rescheduled_at', 'rescheduled_by']);
        });

        Schema::table('consultant_records', function (Blueprint $table) {
            $table->dropForeign(['rescheduled_by']);
            $table->dropColumn(['original_follow_up_date', 'rescheduled_reason', 'rescheduled_at', 'rescheduled_by']);
        });
    }
};
