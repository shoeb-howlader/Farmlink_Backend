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
            $table->string('recipient_name')->nullable()->after('user_id');
            $table->string('recipient_phone')->nullable()->after('recipient_name');
            $table->text('delivery_address')->nullable()->after('recipient_phone');

            // Geocoded location foreign keys
            $table->foreignId('division_id')->nullable()->after('delivery_address')->constrained('divisions')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('division_id')->constrained('districts')->nullOnDelete();
            $table->foreignId('upazila_id')->nullable()->after('district_id')->constrained('upazilas')->nullOnDelete();
            $table->foreignId('union_id')->nullable()->after('upazila_id')->constrained('unions')->nullOnDelete();
            $table->foreignId('pourashava_id')->nullable()->after('union_id')->constrained('pourashavas')->nullOnDelete();

            // Human-readable denormalized names for invoices/receipts
            $table->string('district')->nullable()->after('pourashava_id');
            $table->string('upazila')->nullable()->after('district');
            $table->string('union')->nullable()->after('upazila');

            $table->index('district_id');
            $table->index('upazila_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['district_id']);
            $table->dropIndex(['upazila_id']);

            $table->dropForeign(['division_id']);
            $table->dropForeign(['district_id']);
            $table->dropForeign(['upazila_id']);
            $table->dropForeign(['union_id']);
            $table->dropForeign(['pourashava_id']);

            $table->dropColumn([
                'recipient_name',
                'recipient_phone',
                'delivery_address',
                'division_id',
                'district_id',
                'upazila_id',
                'union_id',
                'pourashava_id',
                'district',
                'upazila',
                'union',
            ]);
        });
    }
};
