<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AdminNotification;
use App\Models\Farm;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VetRecord;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminEnterpriseEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_self_profile_update_and_password_change(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'phone' => '01711223344',
            'password' => Hash::make('oldpassword123'),
        ]);
        $admin->syncRoles(['admin']);

        // Update profile
        $updateRes = $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/me', [
            'name' => 'New Admin Name',
            'phone' => '01711223344',
            'email' => 'newadmin@farmlink.com',
            'district' => 'Dhaka Central',
            'gender' => 'male',
            'avatar_url' => 'https://example.com/avatar.jpg',
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'New Admin Name')
            ->assertJsonPath('data.avatar_url', 'https://example.com/avatar.jpg');

        $admin->refresh();
        $this->assertEquals('New Admin Name', $admin->name);
        $this->assertEquals('https://example.com/avatar.jpg', $admin->avatar_url);

        // Update password with wrong current password
        $failPwRes = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/me/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $failPwRes->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        // Update password with correct current password
        $okPwRes = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/me/password', [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $okPwRes->assertStatus(200);

        $admin->refresh();
        $this->assertTrue(Hash::check('newpassword123', $admin->password));
    }

    public function test_login_updates_last_login_at(): void
    {
        $admin = User::factory()->create([
            'phone' => '01799887766',
            'password' => Hash::make('secret123'),
            'last_login_at' => null,
        ]);
        $admin->syncRoles(['admin']);

        $response = $this->postJson('/api/v1/login', [
            'phone' => '01799887766',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $admin->refresh();
        $this->assertNotNull($admin->last_login_at);
    }

    public function test_audit_logs_can_be_listed_and_retrieved(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        ActivityLog::log('create_product', Product::class, ['name' => 'Fish Feed'], $admin->id, 1);
        ActivityLog::log('update_order_status', Order::class, ['status' => 'delivered'], $admin->id, 5);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/audit-log');
        $res->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $recentRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/audit-log/recent');
        $recentRes->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_notifications_lifecycle(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $n1 = AdminNotification::notify('order', 'New Order', 'Order #1 placed', ['order_id' => 1]);
        $n2 = AdminNotification::notify('stock', 'Low Stock', 'Feed is low', ['product_id' => 2]);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/notifications');
        $res->assertStatus(200)
            ->assertJsonPath('data.unread_count', 2);

        // Mark single as read
        $readRes = $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/admin/notifications/{$n1->id}/read");
        $readRes->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        // Mark all as read
        $markAllRes = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/notifications/mark-all-read');
        $markAllRes->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_vet_and_consultant_unified_records(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $farmer = User::factory()->create();
        $farmer->syncRoles(['farmer']);

        $vet = User::factory()->create();
        $vet->syncRoles(['veterinary_doctor']);

        $farm = Farm::factory()->create(['user_id' => $farmer->id]);

        VetRecord::factory()->create([
            'farm_id' => $farm->id,
            'vet_id' => $vet->id,
            'findings' => 'White spot syndrome detected',
        ]);

        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/vet-consultant-records');
        $res->assertStatus(200)
            ->assertJsonPath('summary.total_vet', 1)
            ->assertJsonPath('data.0.findings', 'White spot syndrome detected')
            ->assertJsonPath('data.0.record_type', 'vet');
    }

    public function test_reports_and_csv_exports(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $farmer = User::factory()->create(['district' => 'Satkhira']);
        $farmer->syncRoles(['farmer']);

        // Sales report
        $salesRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/reports/sales');
        $salesRes->assertStatus(200)
            ->assertJsonStructure(['data' => ['period', 'metrics', 'status_breakdown', 'top_products']]);

        // Sales export
        $salesCsvRes = $this->actingAs($admin, 'sanctum')->get('/api/v1/admin/reports/sales/export');
        $salesCsvRes->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Farmers report
        $farmersRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/reports/farmers');
        $farmersRes->assertStatus(200)
            ->assertJsonPath('data.metrics.total_farmers', 1);

        // Farmers export
        $farmersCsvRes = $this->actingAs($admin, 'sanctum')->get('/api/v1/admin/reports/farmers/export');
        $farmersCsvRes->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_global_exception_handler_shields_raw_sql(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        // Temporarily register a test route that throws a QueryException
        \Illuminate\Support\Facades\Route::get('/api/v1/test-db-error', function () {
            throw new \Illuminate\Database\QueryException(
                'sqlite',
                'SELECT * FROM secret_internal_table WHERE sensitive_data = 1;',
                [],
                new \Exception('Raw table does not exist')
            );
        });

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/test-db-error');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Something went wrong processing your request. Please try again later.',
            ]);

        // Assert that raw SQL or internal table name was NOT leaked to the response
        $this->assertStringNotContainsString('secret_internal_table', $response->getContent());
        $this->assertStringNotContainsString('SELECT * FROM', $response->getContent());
    }
}
