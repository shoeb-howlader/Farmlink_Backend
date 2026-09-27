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
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['vet', 'consultant']);
            $table->text('description');
            $table->enum('urgency', ['normal', 'urgent'])->default('normal');
            $table->string('photo_path', 500)->nullable();
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('fulfilled_record_type')->nullable();
            $table->unsignedBigInteger('fulfilled_record_id')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('feedback_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'urgency']);
            $table->index('assigned_to');
            $table->index('farmer_id');
            $table->index('farm_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
