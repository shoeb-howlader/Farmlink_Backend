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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female', 'unspecified'])->after('district');
            $table->string('profile_image_path', 500)->nullable()->after('gender');
            $table->string('profile_image_thumbnail_path', 500)->nullable()->after('profile_image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'profile_image_path', 'profile_image_thumbnail_path']);
        });
    }
};
