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
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 1. Basic Farm Info
            $table->string('farm_name');
            $table->string('farm_type'); // Own, Leased, Family, Partnership, Other
            $table->decimal('total_area', 10, 2);
            $table->unsignedInteger('pond_count')->default(1);
            $table->decimal('cultivation_area', 10, 2)->nullable();

            // 2. Farm Location
            $table->string('district');
            $table->string('upazila');
            $table->string('union')->nullable();
            $table->string('village')->nullable();
            $table->text('farm_address')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();

            // 3. Farming Experience
            $table->unsignedSmallInteger('aquaculture_experience_years')->nullable();
            $table->string('previous_farming_experience')->nullable(); // Shrimp, Golda, Fish, Mixed, Other

            // 4. Main Farming Profile
            $table->string('main_culture_type'); // Bagda, Golda, Tilapia, Pangas, Carp, Other
            $table->string('farming_system'); // Extensive, Improved Extensive, Semi-intensive, Intensive, Other

            // 5. Water Source
            $table->string('main_water_source')->nullable(); // River, Canal, Deep Tubewell, etc.
            $table->string('water_exchange_facility')->nullable();
            $table->string('water_source_distance')->nullable();

            // 6. Farm Infrastructure
            $table->json('available_facilities')->nullable(); // Electric, Generator, Solar, Pump, Aerator, etc.
            $table->unsignedInteger('aerator_count')->nullable();
            $table->string('aerator_hp')->nullable();
            $table->decimal('aerator_hours_per_day', 4, 1)->nullable();

            // 7. Farm Management
            $table->string('farm_manager')->nullable(); // Self, Family, Hired Manager, etc.
            $table->string('technical_support_used')->nullable();
            $table->string('main_advice_source')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
