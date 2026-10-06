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
        Schema::create('canned_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 30)->default('admin'); // 'admin' or 'practitioner'
            $table->string('shortcut', 50)->index(); // e.g. /hours, /water-test
            $table->string('title', 150);
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canned_responses');
    }
};
