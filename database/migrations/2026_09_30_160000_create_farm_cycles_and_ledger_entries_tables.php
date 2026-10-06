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
        Schema::create('farm_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->string('label', 100);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['farm_id', 'start_date']);
            $table->index(['farm_id', 'end_date']);
        });

        Schema::create('farm_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('farm_cycles')->nullOnDelete();
            $table->enum('entry_type', ['income', 'expense']);
            $table->string('category', 50)->default('Other');
            $table->decimal('amount', 12, 2);
            $table->date('entry_date');
            $table->text('note')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('source', ['manual', 'system_order', 'system_service'])->default('manual');
            $table->unsignedBigInteger('source_reference_id')->nullable();
            $table->foreignId('entered_by')->constrained('users')->cascadeOnDelete();
            $table->string('entered_by_role', 20); // farmer | deo | admin
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index('farm_id');
            $table->index('cycle_id');
            $table->index('entry_date');
            $table->index('entry_type');
            $table->index('source');
            $table->index(['source', 'source_reference_id']);
            $table->index('entered_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_ledger_entries');
        Schema::dropIfExists('farm_cycles');
    }
};
