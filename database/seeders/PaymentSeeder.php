<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds for payments & reconciliation.
     */
    public function run(): void
    {
        $admin = User::role('admin')->first();
        $orders = Order::with('items')->get();

        if ($orders->isEmpty()) {
            return;
        }

        $paymentGateways = [
            [
                'method' => 'sslcommerz',
                'card_type' => 'BKASH-BKash',
                'card_issuer' => 'bKash Mobile Money',
                'card_brand' => 'BKASH',
            ],
            [
                'method' => 'sslcommerz',
                'card_type' => 'NAGAD-Nagad',
                'card_issuer' => 'Nagad Digital Financial Services',
                'card_brand' => 'NAGAD',
            ],
            [
                'method' => 'sslcommerz',
                'card_type' => 'VISA-Dutch Bangla Bank',
                'card_issuer' => 'Dutch Bangla Bank Ltd',
                'card_brand' => 'VISA',
            ],
            [
                'method' => 'cash',
                'notes' => 'Direct cash payment received at outlet.',
            ],
            [
                'method' => 'cod',
                'notes' => 'Cash on delivery payment collected pondside.',
            ],
        ];

        foreach ($orders as $index => $order) {
            // Skip if already has payment
            if (Payment::where('order_id', $order->id)->exists()) {
                continue;
            }

            $gateway = $paymentGateways[$index % count($paymentGateways)];
            $amount = $order->total > 0 ? (float) $order->total : 1500.00;

            if ($gateway['method'] === 'sslcommerz') {
                $status = ($index === 3) ? 'failed' : 'success';
                $tranId = 'SSL_' . strtoupper(Str::random(10));

                $payment = Payment::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'method' => 'sslcommerz',
                    'amount' => $amount,
                    'status' => $status,
                    'gateway_transaction_id' => $tranId,
                    'gateway_response' => [
                        'status' => $status === 'success' ? 'VALID' : 'FAILED',
                        'tran_id' => $tranId,
                        'val_id' => 'VAL_' . strtoupper(Str::random(8)),
                        'amount' => $amount,
                        'card_type' => $gateway['card_type'],
                        'card_brand' => $gateway['card_brand'],
                        'card_issuer' => $gateway['card_issuer'],
                        'bank_tran_id' => 'BANK_' . strtoupper(Str::random(8)),
                    ],
                    'paid_at' => $status === 'success' ? ($order->paid_at ?? $order->created_at ?? now()) : null,
                    'notes' => $status === 'failed' ? 'Customer cancelled payment during gateway checkout.' : 'Online payment verified via SSLCommerz IPN.',
                    'created_at' => $order->created_at ?? now(),
                    'updated_at' => $order->updated_at ?? now(),
                ]);

                // Create a sample refund for one completed transaction
                if ($index === 4 && $status === 'success') {
                    $refundAmount = round($amount * 0.5, 2);
                    Refund::create([
                        'payment_id' => $payment->id,
                        'order_id' => $order->id,
                        'amount' => $refundAmount,
                        'reason' => 'Customer requested partial refund due to damaged packaging on feed pellets.',
                        'status' => 'completed',
                        'restock_items' => false,
                        'gateway_refund_ref' => 'REF_' . strtoupper(Str::random(8)),
                        'gateway_response' => [
                            'status' => 'success',
                            'refund_ref_id' => 'REF_' . strtoupper(Str::random(8)),
                        ],
                        'initiated_by' => $admin?->id,
                        'completed_at' => now()->subDay(),
                    ]);

                    $payment->update(['status' => 'partially_refunded']);
                }
            } elseif ($gateway['method'] === 'cash') {
                Payment::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'method' => 'cash',
                    'amount' => $amount,
                    'status' => 'success',
                    'paid_at' => $order->created_at ?? now(),
                    'notes' => $gateway['notes'],
                    'created_at' => $order->created_at ?? now(),
                    'updated_at' => $order->updated_at ?? now(),
                ]);
            } else { // cod
                $isDelivered = ($order->status === 'delivered');
                Payment::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'method' => 'cod',
                    'amount' => $amount,
                    'status' => $isDelivered ? 'success' : 'pending',
                    'paid_at' => $isDelivered ? ($order->updated_at ?? now()) : null,
                    'notes' => $isDelivered ? 'Cash collected upon delivery by courier.' : 'Pending collection upon pondside delivery.',
                    'created_at' => $order->created_at ?? now(),
                    'updated_at' => $order->updated_at ?? now(),
                ]);
            }
        }
    }
}
