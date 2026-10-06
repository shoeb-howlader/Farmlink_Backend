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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index();
            $table->enum('type', ['percentage', 'fixed_amount'])->default('fixed_amount');
            $table->decimal('value', 10, 2);
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_farmer')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('discount_amount', 10, 2);
            $table->dateTime('redeemed_at');
            $table->timestamps();

            $table->index(['coupon_id', 'farmer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
