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
        Schema::create('pond_telemetries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('pond_name')->nullable()->default('Pond 1');
            $table->timestamp('recorded_at')->useCurrent();
            $table->decimal('dissolved_oxygen', 5, 2)->nullable()->comment('mg/L or ppm');
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('salinity', 5, 2)->nullable()->comment('ppt');
            $table->decimal('temperature', 5, 2)->nullable()->comment('Celsius');
            $table->decimal('transparency', 5, 2)->nullable()->comment('Secchi disc depth in cm');
            $table->decimal('ammonia', 5, 3)->nullable()->comment('NH3/NH4 in mg/L');
            $table->decimal('alkalinity', 6, 2)->nullable()->comment('CaCO3 in mg/L');
            $table->string('weather_condition')->nullable()->comment('sunny, cloudy, rainy, windy');
            $table->text('notes')->nullable();
            $table->string('sync_source')->default('online')->comment('online, offline_sync');
            $table->string('client_uuid', 64)->nullable()->unique()->comment('Idempotency UUID for offline sync');
            $table->timestamps();

            $table->index(['farm_id', 'recorded_at']);
            $table->index(['user_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pond_telemetries');
    }
};
