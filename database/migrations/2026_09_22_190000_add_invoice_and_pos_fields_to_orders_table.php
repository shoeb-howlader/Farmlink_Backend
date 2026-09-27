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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->after('id');
            $table->string('channel')->default('self_service')->after('total'); // 'self_service', 'admin_pos'
            $table->string('payment_mode')->default('cod')->after('channel'); // 'cash', 'cod', 'farmer_credit'
            $table->text('notes')->nullable()->after('payment_mode');
        });

        // Retrofit all existing orders with sequential, human-readable invoice numbers (INV-YYYY-XXXXX)
        $orders = DB::table('orders')->orderBy('id', 'asc')->get();
        $counterByYear = [];

        foreach ($orders as $order) {
            $year = $order->created_at ? date('Y', strtotime($order->created_at)) : date('Y');
            if (! isset($counterByYear[$year])) {
                $counterByYear[$year] = 1;
            } else {
                $counterByYear[$year]++;
            }

            $invoiceNumber = sprintf('INV-%s-%05d', $year, $counterByYear[$year]);

            DB::table('orders')->where('id', $order->id)->update([
                'invoice_number' => $invoiceNumber,
                'channel' => 'self_service',
                'payment_mode' => 'cod',
            ]);
        }

        // Add unique constraint after retrofitting
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_number', 'channel', 'payment_mode', 'notes']);
        });
    }
};
