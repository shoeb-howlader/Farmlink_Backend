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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('phone_otp', 10)->nullable()->after('phone_verified_at');
            $table->timestamp('phone_otp_expires_at')->nullable()->after('phone_otp');
            $table->timestamp('phone_otp_sent_at')->nullable()->after('phone_otp_expires_at');
        });

        // Pre-verify all existing users so demo accounts and existing tests remain active
        DB::table('users')->whereNull('phone_verified_at')->update([
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'phone_otp', 'phone_otp_expires_at', 'phone_otp_sent_at']);
        });
    }
};
