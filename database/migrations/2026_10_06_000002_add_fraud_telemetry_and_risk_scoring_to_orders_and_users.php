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
        Schema::table('orders', function (Blueprint $table) {
            // Client Telemetry
            $table->string('ip_address', 45)->nullable()->after('payment_mode');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('ip_country', 2)->default('BD')->after('user_agent');

            // Automated Risk Assessment
            $table->unsignedTinyInteger('risk_score')->default(0)->after('ip_country');
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low')->after('risk_score');
            $table->boolean('is_flagged')->default(false)->after('risk_level');
            $table->json('flag_reasons')->nullable()->after('is_flagged');

            // Staff Verification Workflow
            $table->enum('verification_status', [
                'unverified',
                'verified_call',
                'advance_paid',
                'auto_trusted',
            ])->default('unverified')->after('flag_reasons');
            $table->foreignId('verified_by')->nullable()->after('verification_status')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });

        Schema::table('users', function (Blueprint $table) {
            // Customer Trust & COD Reputation
            $table->unsignedInteger('delivered_orders_count')->default(0)->after('status');
            $table->unsignedInteger('refused_cod_orders_count')->default(0)->after('delivered_orders_count');
            $table->boolean('cod_blocked')->default(false)->after('refused_cod_orders_count');
            $table->boolean('is_blacklisted')->default(false)->after('cod_blocked');
            $table->string('blacklist_reason')->nullable()->after('is_blacklisted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'ip_address',
                'user_agent',
                'ip_country',
                'risk_score',
                'risk_level',
                'is_flagged',
                'flag_reasons',
                'verification_status',
                'verified_by',
                'verified_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_orders_count',
                'refused_cod_orders_count',
                'cod_blocked',
                'is_blacklisted',
                'blacklist_reason',
            ]);
        });
    }
};
