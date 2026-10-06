<?php

use App\Models\PaymentMethod;
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
            $table->string('payment_status', 50)->default('unpaid')->after('payment_mode')->index();
            $table->string('gateway_transaction_id', 100)->nullable()->after('payment_status')->index();
            $table->json('gateway_payment_details')->nullable()->after('gateway_transaction_id');
            $table->dateTime('paid_at')->nullable()->after('gateway_payment_details');
            $table->string('refund_status', 50)->default('none')->after('paid_at')->index();
            $table->string('refund_reference', 100)->nullable()->after('refund_status');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_reference');
            $table->dateTime('refunded_at')->nullable()->after('refund_amount');
            $table->text('refund_reason')->nullable()->after('refunded_at');
        });

        // Seed SSLCommerz in Payment Methods lookup table
        PaymentMethod::firstOrCreate(
            ['code' => 'sslcommerz'],
            [
                'name' => 'Online Payment (SSLCommerz)',
                'description' => 'Pay securely via Debit/Credit Cards, Mobile Banking (bKash, Nagad, Rocket), or Net Banking.',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'gateway_transaction_id',
                'gateway_payment_details',
                'paid_at',
                'refund_status',
                'refund_reference',
                'refund_amount',
                'refunded_at',
                'refund_reason',
            ]);
        });

        PaymentMethod::where('code', 'sslcommerz')->delete();
    }
};
