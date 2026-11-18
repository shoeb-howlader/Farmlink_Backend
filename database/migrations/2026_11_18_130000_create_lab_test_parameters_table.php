<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lab_test_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('category')->default('water'); // 'water', 'soil', 'microbiology', 'other'
            $table->string('default_unit', 50)->nullable();
            $table->string('default_range', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('category');
        });

        // Seed Initial Standard Aquaculture Diagnostic Parameters
        $now = now();
        $initialParameters = [
            [
                'name' => 'Dissolved Oxygen (DO)',
                'name_bn' => 'অক্সিজেন (DO)',
                'category' => 'water',
                'default_unit' => 'mg/L',
                'default_range' => '5.0 - 8.0',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'pH Level',
                'name_bn' => 'পানির পিএইচ (pH)',
                'category' => 'water',
                'default_unit' => null,
                'default_range' => '6.5 - 8.5',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Total Ammonia (TAN)',
                'name_bn' => 'অ্যামোনিয়া (TAN)',
                'category' => 'water',
                'default_unit' => 'ppm',
                'default_range' => '< 0.1',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Salinity',
                'name_bn' => 'লবণাক্ততা (Salinity)',
                'category' => 'water',
                'default_unit' => 'ppt',
                'default_range' => '10 - 25',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Water Temperature',
                'name_bn' => 'পানির তাপমাত্রা',
                'category' => 'water',
                'default_unit' => '°C',
                'default_range' => '26 - 32',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Alkalinity',
                'name_bn' => 'ক্ষারত্ব (Alkalinity)',
                'category' => 'water',
                'default_unit' => 'mg/L',
                'default_range' => '80 - 120',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Nitrite (NO2-)',
                'name_bn' => 'নাইট্রাইট (Nitrite)',
                'category' => 'water',
                'default_unit' => 'ppm',
                'default_range' => '< 0.2',
                'sort_order' => 7,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Soil pH',
                'name_bn' => 'মাটির পিএইচ',
                'category' => 'soil',
                'default_unit' => null,
                'default_range' => '6.0 - 7.5',
                'sort_order' => 8,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('lab_test_parameters')->insert($initialParameters);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_parameters');
    }
};
