<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\District;
use App\Models\DistrictDeliveryRate;
use App\Models\Division;
use App\Models\Farm;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Taxonomy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsAndCouponsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $farmer;
    protected User $deo;
    protected Farm $farm;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->farmer = User::factory()->create([
            'phone_verified_at' => now(),
        ]);
        $this->farmer->assignRole('farmer');

        $this->deo = User::factory()->create();
        $this->deo->assignRole('data_entry_operator');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Sundarbans Aqua Pond',
            'district' => 'Bagerhat',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Prawn Feed Mega 25kg',
            'price' => 1000.00,
            'stock' => 100,
            'is_active' => true,
        ]);

        // Default settings
        Setting::set('flat_delivery_fee', 60.00, 'delivery');
        Setting::set('free_delivery_threshold', 2500.00, 'delivery');
    }

    public function test_delivery_fee_applied_below_threshold_and_free_above_threshold(): void
    {
        // 1. Order below threshold (1000 < 2500) -> delivery fee = 60.00, total = 1060.00
        $response1 = $this->actingAs($this->farmer)
            ->postJson('/api/v1/orders', [
                'farm_id' => $this->farm->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
                'payment_mode' => 'cod',
            ]);

        $response1->assertCreated();
        $order1 = Order::find($response1->json('data.id'));
        $this->assertEquals(1000.00, (float) $order1->subtotal);
        $this->assertEquals(60.00, (float) $order1->delivery_fee);
        $this->assertEquals(1060.00, (float) $order1->total);

        // 2. Order above threshold (3000 >= 2500) -> delivery fee = 0.00, total = 3000.00
        $response2 = $this->actingAs($this->farmer)
            ->postJson('/api/v1/orders', [
                'farm_id' => $this->farm->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 3],
                ],
                'payment_mode' => 'cod',
            ]);

        $response2->assertCreated();
        $order2 = Order::find($response2->json('data.id'));
        $this->assertEquals(3000.00, (float) $order2->subtotal);
        $this->assertEquals(0.00, (float) $order2->delivery_fee);
        $this->assertEquals(3000.00, (float) $order2->total);

        // 3. Changing settings later does not alter historical order recorded delivery fee
        Setting::set('flat_delivery_fee', 120.00, 'delivery');
        $freshOrder1 = Order::find($order1->id);
        $this->assertEquals(60.00, (float) $freshOrder1->delivery_fee);
    }

    public function test_settings_update_and_audit_trail_logging(): void
    {
        // DEO cannot update settings
        $responseDeo = $this->actingAs($this->deo)
            ->putJson('/api/v1/admin/settings', [
                'group' => 'general',
                'settings' => [
                    'site_name' => 'Hacked Farmlink',
                ],
            ]);
        $responseDeo->assertForbidden();

        // Admin updates settings
        $responseAdmin = $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'group' => 'general',
                'settings' => [
                    'site_name' => 'FarmLink Southern Depot',
                    'contact_phone' => '+8801999888777',
                    'flat_delivery_fee' => 75.00,
                ],
            ]);

        $responseAdmin->assertOk();
        $this->assertEquals('FarmLink Southern Depot', Setting::get('site_name'));
        $this->assertEquals(75.00, (float) Setting::get('flat_delivery_fee'));

        // Verify audit log created
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'setting.updated',
            'user_id' => $this->admin->id,
        ]);

        // Verify GET endpoint returns grouped settings and recent logs with actor
        $responseIndex = $this->actingAs($this->admin)->getJson('/api/v1/admin/settings');
        $responseIndex->assertOk()
            ->assertJsonPath('data.settings.general.site_name', 'FarmLink Southern Depot');

        $this->assertNotEmpty($responseIndex->json('data.recent_logs'));
        $this->assertEquals($this->admin->id, $responseIndex->json('data.recent_logs.0.actor.id'));
    }

    public function test_fraud_and_risk_settings_can_be_updated_by_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'group' => 'fraud',
                'settings' => [
                    'fraud_detection_enabled' => true,
                    'bulk_cod_threshold' => 15000,
                    'cod_max_doorstep_refusals' => 3,
                    'advance_delivery_fee_enabled' => true,
                    'advance_delivery_fee_threshold' => 6000,
                    'advance_delivery_fee_amount' => 200,
                    'auto_confirm_on_phone_verified' => true,
                    'ip_geolocation_enabled' => false,
                ],
            ]);

        $response->assertOk();
        $this->assertEquals('15000', Setting::get('bulk_cod_threshold'));
        $this->assertEquals('3', Setting::get('cod_max_doorstep_refusals'));
        $this->assertEquals('6000', Setting::get('advance_delivery_fee_threshold'));
        $this->assertEquals('200', Setting::get('advance_delivery_fee_amount'));
        $this->assertEquals('0', Setting::get('ip_geolocation_enabled'));
    }

    public function test_coupon_validation_rules_and_rejections(): void
    {
        // Inactive coupon
        $inactiveCoupon = Coupon::create([
            'code' => 'INACTIVE10',
            'type' => 'percentage',
            'value' => 10,
            'active' => false,
        ]);

        $res1 = $this->actingAs($this->farmer)->postJson('/api/v1/coupons/validate', [
            'code' => 'INACTIVE10',
            'subtotal' => 1000,
        ]);
        $res1->assertStatus(422);

        // Expired coupon
        $expiredCoupon = Coupon::create([
            'code' => 'EXPIRED50',
            'type' => 'fixed_amount',
            'value' => 50,
            'expires_at' => now()->subDay(),
            'active' => true,
        ]);

        $res2 = $this->actingAs($this->farmer)->postJson('/api/v1/coupons/validate', [
            'code' => 'EXPIRED50',
            'subtotal' => 1000,
        ]);
        $res2->assertStatus(422);

        // Minimum order amount not met
        $minOrderCoupon = Coupon::create([
            'code' => 'BIGBUY',
            'type' => 'fixed_amount',
            'value' => 100,
            'minimum_order_amount' => 5000,
            'active' => true,
        ]);

        $res3 = $this->actingAs($this->farmer)->postJson('/api/v1/coupons/validate', [
            'code' => 'BIGBUY',
            'subtotal' => 1000,
        ]);
        $res3->assertStatus(422);
    }

    public function test_valid_coupon_reduces_order_total_and_records_redemption_atomically(): void
    {
        $coupon = Coupon::create([
            'code' => 'FARMER15',
            'type' => 'percentage',
            'value' => 15, // 15% off
            'minimum_order_amount' => 500,
            'usage_limit_total' => 5,
            'usage_limit_per_farmer' => 1,
            'active' => true,
        ]);

        // Validate endpoint
        $resValidate = $this->actingAs($this->farmer)->postJson('/api/v1/coupons/validate', [
            'code' => 'FARMER15',
            'subtotal' => 1000,
        ]);
        $resValidate->assertOk();
        $this->assertEquals(150.00, (float) $resValidate->json('data.discount'));

        // Submit order with coupon
        // Subtotal = 1000, Discount = 150 (15%), Delivery = 60, Total = 910
        $resOrder = $this->actingAs($this->farmer)->postJson('/api/v1/orders', [
            'farm_id' => $this->farm->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'coupon_code' => 'FARMER15',
            'payment_mode' => 'cod',
        ]);

        $resOrder->assertCreated();
        $order = Order::find($resOrder->json('data.id'));

        $this->assertEquals(1000.00, (float) $order->subtotal);
        $this->assertEquals(150.00, (float) $order->discount_amount);
        $this->assertEquals(60.00, (float) $order->delivery_fee);
        $this->assertEquals(910.00, (float) $order->total);
        $this->assertEquals($coupon->id, $order->coupon_id);

        // Verify CouponRedemption record created atomically
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'farmer_id' => $this->farmer->id,
        ]);

        // Attempting to use again should fail due to usage_limit_per_farmer = 1
        $resOrder2 = $this->actingAs($this->farmer)->postJson('/api/v1/orders', [
            'farm_id' => $this->farm->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
            'coupon_code' => 'FARMER15',
            'payment_mode' => 'cod',
        ]);
        $resOrder2->assertStatus(422);
    }

    public function test_taxonomies_lookup_crud_and_reference_protection(): void
    {
        // 1. Create a taxonomy item
        $resCreate = $this->actingAs($this->admin)->postJson('/api/v1/admin/taxonomies', [
            'type' => 'farm_type',
            'name' => 'Cooperative Pond Cluster',
            'slug' => 'coop_cluster',
            'description' => 'Multiple farmers operating under common management',
            'is_active' => true,
        ]);
        $resCreate->assertCreated();
        $taxId = $resCreate->json('data.id');

        // 2. Active taxonomies endpoint returns it
        $resPublic = $this->getJson('/api/v1/taxonomies?type=farm_type');
        $resPublic->assertOk();
        $this->assertTrue(collect($resPublic->json('data'))->contains('slug', 'coop_cluster'));

        // 3. Assign this taxonomy slug to a farm
        $this->farm->farm_type = 'coop_cluster';
        $this->farm->save();

        // 4. Attempting to hard-delete referenced taxonomy must be rejected
        $resDelete = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/taxonomies/{$taxId}");
        $resDelete->assertStatus(422);
        $this->assertDatabaseHas('taxonomies', ['id' => $taxId]);

        // 5. Deactivating it is allowed
        $resToggle = $this->actingAs($this->admin)->patchJson("/api/v1/admin/taxonomies/{$taxId}/toggle");
        $resToggle->assertOk();
        $this->assertFalse((bool) Taxonomy::find($taxId)->is_active);

        // 6. Inactive taxonomy no longer appears in public dropdown list
        $resPublicAfter = $this->getJson('/api/v1/taxonomies?type=farm_type');
        $this->assertFalse(collect($resPublicAfter->json('data'))->contains('slug', 'coop_cluster'));
    }

    public function test_payment_methods_active_toggle(): void
    {
        $method = PaymentMethod::create([
            'code' => 'rocket',
            'name' => 'DBBL Rocket Mobile',
            'is_active' => true,
        ]);

        $res1 = $this->getJson('/api/v1/payment-methods');
        $res1->assertOk();
        $this->assertTrue(collect($res1->json('data'))->contains('code', 'rocket'));

        // Toggle to inactive
        $this->actingAs($this->admin)->patchJson("/api/v1/admin/payment-methods/{$method->id}/toggle");

        $res2 = $this->getJson('/api/v1/payment-methods');
        $res2->assertOk();
        $this->assertFalse(collect($res2->json('data'))->contains('code', 'rocket'));
    }

    public function test_admin_can_upload_and_remove_organization_logo_and_it_reflects_in_public_settings(): void
    {
        Storage::fake('public');

        // 1. Non-admin cannot upload logo
        $file = UploadedFile::fake()->image('logo.png', 400, 100);
        $resFarmer = $this->actingAs($this->farmer)->postJson('/api/v1/admin/settings/logo', [
            'image' => $file,
        ]);
        $resFarmer->assertForbidden();

        // 2. Admin uploads logo
        $resAdmin = $this->actingAs($this->admin)->postJson('/api/v1/admin/settings/logo', [
            'image' => $file,
        ]);
        $resAdmin->assertOk()
            ->assertJsonPath('success', true);

        $logoUrl = $resAdmin->json('data.logo_url');
        $this->assertNotEmpty($logoUrl);
        $this->assertEquals($logoUrl, Setting::get('site_logo_url'));

        // 3. Verify public settings returns the logo URL
        $resPublic = $this->getJson('/api/v1/settings/public');
        $resPublic->assertOk()
            ->assertJsonPath('data.site_logo_url', $logoUrl);

        // 4. Verify audit log entry
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'setting.updated',
            'user_id' => $this->admin->id,
        ]);

        // 5. Admin removes logo
        $resRemove = $this->actingAs($this->admin)->deleteJson('/api/v1/admin/settings/logo');
        $resRemove->assertOk()
            ->assertJsonPath('data.logo_url', null);

        $this->assertNull(Setting::get('site_logo_url'));

        // 6. Public settings reflects removed logo
        $resPublicAfter = $this->getJson('/api/v1/settings/public');
        $resPublicAfter->assertOk()
            ->assertJsonPath('data.site_logo_url', null);
    }
}
