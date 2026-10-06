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
        Schema::create('reschedule_requests', function (Blueprint $table) {
            $table->id();
            $table->string('record_type'); // 'vet' or 'consultant'
            $table->unsignedBigInteger('record_id');
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();
            $table->date('old_date');
            $table->date('new_date');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['record_type', 'record_id']);
            $table->index('status');
            $table->index('practitioner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reschedule_requests');
    }
};
