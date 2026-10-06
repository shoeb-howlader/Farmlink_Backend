<?php

namespace Tests\Feature\Mail;

use App\Mail\AdminDigestMail;
use App\Mail\CriticalSystemAlertMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderStatusChangedMail;
use App\Mail\PasswordResetMail;
use App\Mail\PractitionerAssignmentMail;
use App\Mail\VisitReportMail;
use App\Models\Farm;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use App\Services\SystemAlertService;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TransactionalEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_farmer_registration_succeeds_without_email_and_remains_optional(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Phone Farmer',
            'phone' => '01711223344',
            'gender' => 'male',
            'district' => 'Bogra',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            // No email provided
        ]);

        $response->assertStatus(201);
        $user = User::where('phone', '01711223344')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email);
    }

    public function test_order_placement_queues_confirmation_mail_with_invoice_pdf_when_farmer_has_email(): void
    {
        Mail::fake();

        $farmer = User::factory()->farmer()->create([
            'email' => 'farmer@example.com',
            'phone' => '01711000001',
            'phone_verified_at' => now(),
        ]);
        $farm = Farm::factory()->create(['user_id' => $farmer->id]);
        $product = Product::factory()->create([
            'price' => 150.00,
            'stock' => 20,
            'is_active' => true,
        ]);

        $response = $this->actingAs($farmer, 'sanctum')->postJson('/api/v1/orders', [
            'farm_id' => $farm->id,
            'recipient_name' => 'Farmer John',
            'recipient_phone' => '01711000001',
            'delivery_address' => 'Farm Road 1, Bogra',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201);

        Mail::assertQueued(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use ($farmer) {
            return $mail->hasTo('farmer@example.com')
                && $mail->order->user_id === $farmer->id
                && ! empty($mail->pdfBinary);
        });
    }

    public function test_order_placement_does_not_queue_mail_when_farmer_has_no_email(): void
    {
        Mail::fake();

        $farmer = User::factory()->farmer()->create([
            'email' => null,
            'phone' => '01711000002',
            'phone_verified_at' => now(),
        ]);
        $farm = Farm::factory()->create(['user_id' => $farmer->id]);
        $product = Product::factory()->create([
            'price' => 200.00,
            'stock' => 15,
            'is_active' => true,
        ]);

        $response = $this->actingAs($farmer, 'sanctum')->postJson('/api/v1/orders', [
            'farm_id' => $farm->id,
            'recipient_name' => 'No Email Farmer',
            'recipient_phone' => '01711000002',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201);
        Mail::assertNothingQueued();
    }

    public function test_order_status_change_queues_status_mail_with_invoice_pdf_when_email_exists(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['email' => 'admin@farmlink.com']);
        $farmer = User::factory()->farmer()->create(['email' => 'statusfarmer@example.com']);
        $farm = Farm::factory()->create(['user_id' => $farmer->id]);
        $order = Order::factory()->create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'status' => 'pending',
            'total' => 500.00,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200);

        Mail::assertQueued(OrderStatusChangedMail::class, function (OrderStatusChangedMail $mail) use ($order) {
            return $mail->hasTo('statusfarmer@example.com')
                && $mail->order->id === $order->id
                && $mail->oldStatus === 'pending'
                && $mail->newStatus === 'confirmed'
                && ! empty($mail->pdfBinary);
        });
    }

    public function test_service_request_assignment_queues_mail_to_practitioner_with_details(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['email' => 'admin@farmlink.com']);
        $vet = User::factory()->vet()->create([
            'email' => 'vet@farmlink.com',
            'phone' => '01722000001',
        ]);
        $farmer = User::factory()->farmer()->create(['name' => 'Alim Uddin', 'phone' => '01799000001']);
        $farm = Farm::factory()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Shonar Bangla Dairy',
            'district' => 'Bogra',
        ]);

        $sr = ServiceRequest::create([
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'type' => 'vet',
            'urgency' => 'urgent',
            'status' => 'pending',
            'description' => 'Cattle showing high fever and loss of appetite.',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/service-requests/{$sr->id}/assign", [
            'practitioner_id' => $vet->id,
        ]);

        $response->assertStatus(200);

        Mail::assertQueued(PractitionerAssignmentMail::class, function (PractitionerAssignmentMail $mail) use ($vet, $sr) {
            return $mail->hasTo('vet@farmlink.com')
                && $mail->practitioner->id === $vet->id
                && $mail->serviceRequest->id === $sr->id
                && $mail->serviceRequest->farm->farm_name === 'Shonar Bangla Dairy'
                && $mail->serviceRequest->farmer->name === 'Alim Uddin'
                && $mail->serviceRequest->urgency === 'urgent';
        });
    }

    public function test_service_request_completion_queues_visit_report_mail_with_pdf_to_farmer(): void
    {
        Mail::fake();

        $vet = User::factory()->vet()->create(['email' => 'vet@farmlink.com']);
        $farmer = User::factory()->farmer()->create(['email' => 'farmer_report@example.com']);
        $farm = Farm::factory()->create(['user_id' => $farmer->id]);

        $sr = ServiceRequest::create([
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'type' => 'vet',
            'urgency' => 'normal',
            'status' => 'assigned',
            'assigned_to' => $vet->id,
            'assigned_at' => now()->subDay(),
            'description' => 'Routine visit and diagnosis',
        ]);

        $response = $this->actingAs($vet, 'sanctum')->postJson("/api/v1/farms/{$farm->id}/vet-records", [
            'service_request_id' => $sr->id,
            'animal_type' => 'Cattle',
            'visit_date' => now()->toDateString(),
            'findings' => 'Normal vitals, mild dehydration',
            'symptoms' => 'Mild lethargy',
            'diagnosis' => 'Seasonal viral infection',
            'treatment' => 'Administer electrolyte solution and vitamins',
            'next_follow_up' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(201);

        Mail::assertQueued(VisitReportMail::class, function (VisitReportMail $mail) use ($sr) {
            return $mail->hasTo('farmer_report@example.com')
                && $mail->serviceRequest->id === $sr->id
                && $mail->isVet === true
                && ! empty($mail->pdfBinary);
        });
    }

    public function test_admin_digest_command_queues_digest_mail_to_all_active_admins(): void
    {
        Mail::fake();

        $admin1 = User::factory()->admin()->create(['email' => 'admin1@farmlink.com', 'is_active' => true]);
        $admin2 = User::factory()->admin()->create(['email' => 'admin2@farmlink.com', 'is_active' => true]);

        // Place a recent order and low stock product
        $farmer = User::factory()->farmer()->create();
        Order::factory()->create([
            'user_id' => $farmer->id,
            'total' => 1250.00,
            'status' => 'confirmed',
            'created_at' => now()->subHours(2),
        ]);
        Product::factory()->create([
            'name' => 'Test Low Stock Feed',
            'stock' => 2,
            'is_active' => true,
        ]);

        $exitCode = Artisan::call('farmlink:send-admin-digest', ['--frequency' => 'daily']);
        $this->assertEquals(0, $exitCode);

        Mail::assertQueued(AdminDigestMail::class, function (AdminDigestMail $mail) {
            return $mail->hasTo('admin1@farmlink.com')
                && $mail->stats['ordersCount'] >= 1
                && $mail->stats['salesTotal'] >= 1250.00
                && $mail->stats['lowStockCount'] >= 1;
        });

        Mail::assertQueued(AdminDigestMail::class, function (AdminDigestMail $mail) {
            return $mail->hasTo('admin2@farmlink.com');
        });
    }

    public function test_critical_system_alert_service_queues_alert_and_applies_rate_limiting(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['email' => 'superadmin@farmlink.com', 'is_active' => true]);

        $alertService = app(SystemAlertService::class);

        // First dispatch should queue
        $alertService->sendCriticalAlert(
            'Payment Gateway Disconnect',
            'Timeout connecting to bKash API',
            ['gateway' => 'bkash', 'order_id' => 999]
        );

        Mail::assertQueued(CriticalSystemAlertMail::class, function (CriticalSystemAlertMail $mail) {
            return $mail->hasTo('superadmin@farmlink.com')
                && str_contains($mail->title, 'Payment Gateway Disconnect')
                && str_contains($mail->errorMessage, 'Timeout connecting to bKash API');
        });

        // Rapid duplicate call with identical subject/error should be throttled
        Mail::fake();
        $alertService->sendCriticalAlert(
            'Payment Gateway Disconnect',
            'Timeout connecting to bKash API',
            ['gateway' => 'bkash', 'order_id' => 1000]
        );

        Mail::assertNothingQueued();
    }

    public function test_queue_failing_event_triggers_critical_system_alert_mail(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['email' => 'ops@farmlink.com', 'is_active' => true]);

        $mockJob = \Mockery::mock(Job::class);
        $mockJob->shouldReceive('resolveName')->andReturn('App\\Jobs\\ProcessPaymentJob');
        $mockJob->shouldReceive('getQueue')->andReturn('default');
        $mockJob->shouldReceive('attempts')->andReturn(3);

        event(new JobFailed(
            'database',
            $mockJob,
            new \RuntimeException('Connection to payment gateway timed out after 30s')
        ));

        Mail::assertQueued(CriticalSystemAlertMail::class, function (CriticalSystemAlertMail $mail) {
            return $mail->hasTo('ops@farmlink.com')
                && str_contains($mail->title, 'ProcessPaymentJob')
                && str_contains($mail->errorMessage, 'Connection to payment gateway timed out');
        });
    }

    public function test_all_mailables_render_valid_html_templates(): void
    {
        $farmer = User::factory()->farmer()->create(['email' => 'farmer_render@example.com']);
        $admin = User::factory()->admin()->create(['email' => 'admin_render@example.com']);
        $vet = User::factory()->vet()->create(['email' => 'vet_render@example.com']);
        $farm = Farm::factory()->create(['user_id' => $farmer->id, 'farm_name' => 'Green Valley Farm']);

        $order = Order::factory()->create(['user_id' => $farmer->id, 'farm_id' => $farm->id]);
        $sr = ServiceRequest::create([
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'type' => 'vet',
            'urgency' => 'urgent',
            'status' => 'pending',
            'description' => 'Test diagnosis notes',
        ]);
        $vetRecord = VetRecord::create([
            'farm_id' => $farm->id,
            'vet_id' => $vet->id,
            'service_request_id' => $sr->id,
            'animal_type' => 'Cattle',
            'visit_date' => now()->toDateString(),
            'findings' => 'Normal vitals',
            'symptoms' => 'Loss of appetite',
            'diagnosis' => 'Bacterial fever',
            'treatment' => 'Antibiotics course',
            'next_follow_up' => now()->addDays(5)->toDateString(),
        ]);

        // 1. Password Reset Mail
        $resetMail = new PasswordResetMail($admin, 'sample-token', '123456');
        $html = $resetMail->render();
        $this->assertStringContainsString('Password Reset Request', $html);
        $this->assertStringContainsString('123456', $html);

        // 2. Admin Digest Mail
        $digestMail = new AdminDigestMail($admin, [
            'ordersCount' => 12,
            'salesTotal' => 45000.00,
            'lowStockCount' => 3,
            'lowStockItems' => [['name' => 'Fish Feed A', 'category' => 'Aqua', 'stock' => 2]],
            'pendingRequestsCount' => 5,
            'overdueFollowUpsCount' => 2,
        ], 'daily');
        $html = $digestMail->render();
        $this->assertStringContainsString('Operations Digest', $html);
        $this->assertStringContainsString('45,000.00', $html);

        // 3. Critical Alert Mail
        $alertMail = new CriticalSystemAlertMail('Stuck Queue Worker', 'Worker PID 4512 killed', ['queue' => 'default']);
        $html = $alertMail->render();
        $this->assertStringContainsString('CRITICAL ALERT', $html);
        $this->assertStringContainsString('Worker PID 4512 killed', $html);

        // 4. Order Confirmation Mail
        $orderConfirmMail = new OrderConfirmationMail($order);
        $html = $orderConfirmMail->render();
        $this->assertStringContainsString("Order #{$order->id}", $html);

        // 5. Order Status Changed Mail
        $order->status = 'dispatched';
        $orderStatusMail = new OrderStatusChangedMail($order, 'pending', 'dispatched');
        $html = $orderStatusMail->render();
        $this->assertStringContainsString('Dispatched for Delivery', $html);

        // 6. Visit Report Mail
        $reportMail = new VisitReportMail($sr, $vetRecord, $farmer, $vet, true);
        $html = $reportMail->render();
        $this->assertStringContainsString('Visit Report & Prescription', $html);

        // 7. Practitioner Assignment Mail
        $assignmentMail = new PractitionerAssignmentMail($sr, $vet);
        $html = $assignmentMail->render();
        $this->assertStringContainsString('New Service Request Assignment', $html);
        $this->assertStringContainsString(e($farm->farm_name), $html);
    }
}
