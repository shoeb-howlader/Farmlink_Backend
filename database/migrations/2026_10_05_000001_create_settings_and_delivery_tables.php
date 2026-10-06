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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index();
            $table->text('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->timestamps();
        });

        Schema::create('district_delivery_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('districts')->cascadeOnDelete();
            $table->decimal('fee', 10, 2);
            $table->decimal('free_delivery_threshold', 10, 2)->nullable();
            $table->timestamps();
        });

        // Seed initial platform defaults
        $now = now();
        $defaults = [
            // General settings
            ['key' => 'site_name', 'value' => 'FarmLink', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'contact_phone', 'value' => '+880 1700-000000', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'contact_email', 'value' => 'support@farmlink.com.bd', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'currency_symbol', 'value' => '৳', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'currency_code', 'value' => 'BDT', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'business_hours', 'value' => 'Sat - Thu: 9:00 AM - 6:00 PM (Friday Closed)', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],

            // Delivery & Shipping settings
            ['key' => 'flat_delivery_fee', 'value' => '50.00', 'group' => 'delivery', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'free_delivery_threshold', 'value' => '2000.00', 'group' => 'delivery', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('settings')->insert($defaults);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('district_delivery_rates');
        Schema::dropIfExists('settings');
    }
};
