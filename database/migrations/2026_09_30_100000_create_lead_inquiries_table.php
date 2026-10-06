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
        Schema::create('lead_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 25);
            $table->string('email', 100)->nullable();
            $table->string('topic', 100)->nullable();
            $table->text('message');
            $table->string('source', 50)->default('homepage_widget');
            $table->string('status', 30)->default('new'); // new, contacted, converted, closed, spam
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_inquiries');
    }
};
