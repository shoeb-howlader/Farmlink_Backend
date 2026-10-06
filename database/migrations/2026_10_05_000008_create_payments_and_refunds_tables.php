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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 50)->default('cod')->index(); // sslcommerz, cash, cod, credit
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('status', 50)->default('pending')->index(); // pending, success, failed, refunded, partially_refunded
            $table->string('gateway_transaction_id', 100)->nullable()->index();
            $table->json('gateway_response')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->string('status', 50)->default('completed')->index(); // requested, processing, completed, failed
            $table->boolean('restock_items')->default(false);
            $table->string('gateway_refund_ref', 100)->nullable();
            $table->json('gateway_response')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        // Backfill payment records for all existing orders
        $orders = DB::table('orders')->get();
        foreach ($orders as $order) {
            $status = 'pending';
            if ($order->payment_status === 'paid') {
                $status = 'success';
            } elseif ($order->payment_status === 'failed' || $order->status === 'cancelled') {
                $status = $order->payment_status === 'failed' ? 'failed' : ($order->status === 'cancelled' ? 'failed' : 'pending');
            }

            if ($order->refund_status === 'completed') {
                $status = ($order->refund_amount && $order->refund_amount < $order->total) ? 'partially_refunded' : 'refunded';
            }

            DB::table('payments')->insert([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'method' => $order->payment_mode ?? 'cod',
                'amount' => $order->total,
                'status' => $status,
                'gateway_transaction_id' => $order->gateway_transaction_id,
                'gateway_response' => $order->gateway_payment_details,
                'paid_at' => $order->paid_at,
                'created_at' => $order->created_at ?? now(),
                'updated_at' => $order->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
