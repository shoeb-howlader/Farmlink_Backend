<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Farm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusEnumAndDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_enum_values_match_expected_domain_definitions(): void
    {
        $orderValues = OrderStatus::values();
        $this->assertContains('pending_payment', $orderValues);
        $this->assertContains('paid_needs_review', $orderValues);
        $this->assertContains('pending', $orderValues);
        $this->assertContains('confirmed', $orderValues);
        $this->assertContains('dispatched', $orderValues);
        $this->assertContains('delivered', $orderValues);
        $this->assertContains('cancelled', $orderValues);

        $paymentValues = PaymentStatus::values();
        $this->assertContains('pending', $paymentValues);
        $this->assertContains('success', $paymentValues);
        $this->assertContains('failed', $paymentValues);
        $this->assertContains('refunded', $paymentValues);
        $this->assertContains('partially_refunded', $paymentValues);

        $refundValues = RefundStatus::values();
        $this->assertContains('requested', $refundValues);
        $this->assertContains('processing', $refundValues);
        $this->assertContains('completed', $refundValues);
        $this->assertContains('failed', $refundValues);
        $this->assertContains('rejected', $refundValues);
    }

    public function test_database_constraint_rejects_invalid_order_status(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('orders')->insert([
            'user_id' => $user->id,
            'status' => 'non_existent_invalid_status',
            'total' => 100.00,
            'subtotal' => 100.00,
            'delivery_charge' => 0.00,
            'payment_status' => 'unpaid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_constraint_rejects_updating_order_to_invalid_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
        ]);

        $this->expectException(QueryException::class);

        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'malicious_injected_status',
        ]);
    }

    public function test_database_constraint_rejects_invalid_payment_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);

        DB::table('payments')->insert([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'sslcommerz',
            'amount' => 500.00,
            'status' => 'fake_paid_status',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_constraint_rejects_invalid_refund_status(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'sslcommerz',
            'amount' => 500.00,
            'status' => PaymentStatus::SUCCESS->value,
        ]);

        $this->expectException(QueryException::class);

        DB::table('refunds')->insert([
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'amount' => 200.00,
            'reason' => 'Defective',
            'status' => 'invalid_refund_status',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_all_enum_order_statuses_are_accepted_by_database(): void
    {
        $user = User::factory()->create();

        foreach (OrderStatus::cases() as $case) {
            $order = Order::factory()->create([
                'user_id' => $user->id,
                'status' => $case->value,
            ]);

            $this->assertEquals($case->value, $order->status);
        }
    }

    public function test_all_enum_payment_statuses_are_accepted_by_database(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        foreach (PaymentStatus::cases() as $case) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'method' => 'cod',
                'amount' => 150.00,
                'status' => $case->value,
            ]);

            $this->assertEquals($case->value, $payment->status);
        }
    }

    public function test_all_enum_refund_statuses_are_accepted_by_database(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'cod',
            'amount' => 150.00,
            'status' => PaymentStatus::SUCCESS->value,
        ]);

        foreach (RefundStatus::cases() as $case) {
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => 50.00,
                'reason' => 'Testing case ' . $case->value,
                'status' => $case->value,
            ]);

            $this->assertEquals($case->value, $refund->status);
        }
    }

    public function test_migration_and_enums_are_in_perfect_sync(): void
    {
        // Assert each enum has distinct non-empty string values
        $this->assertNotEmpty(OrderStatus::values());
        $this->assertEquals(count(OrderStatus::values()), count(array_unique(OrderStatus::values())));

        $this->assertNotEmpty(PaymentStatus::values());
        $this->assertEquals(count(PaymentStatus::values()), count(array_unique(PaymentStatus::values())));

        $this->assertNotEmpty(RefundStatus::values());
        $this->assertEquals(count(RefundStatus::values()), count(array_unique(RefundStatus::values())));

        // If running directly against PostgreSQL, verify pg_constraint check definition matches
        if (DB::getDriverName() === 'pgsql') {
            $constraints = DB::select("
                SELECT conname, pg_get_constraintdef(c.oid) as def
                FROM pg_constraint c
                WHERE conname IN ('orders_status_check', 'payments_status_check', 'refunds_status_check')
            ");

            $defs = collect($constraints)->pluck('def', 'conname');

            foreach (OrderStatus::values() as $val) {
                $this->assertStringContainsString($val, $defs['orders_status_check'] ?? '');
            }
            foreach (PaymentStatus::values() as $val) {
                $this->assertStringContainsString($val, $defs['payments_status_check'] ?? '');
            }
            foreach (RefundStatus::values() as $val) {
                $this->assertStringContainsString($val, $defs['refunds_status_check'] ?? '');
            }
        }
    }
}

