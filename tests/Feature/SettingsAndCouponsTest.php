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

    public function test_admin_can_configure_pwa_dismissal_duration_and_is_exposed_publicly(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'group' => 'general',
                'settings' => [
                    'pwa_dismissal_duration_days' => 45,
                ],
            ]);

        $response->assertOk();
        $this->assertEquals(45, (int) Setting::get('pwa_dismissal_duration_days'));

        // Verify public settings exposes it
        $publicRes = $this->getJson('/api/v1/settings/public');
        $publicRes->assertOk()
            ->assertJsonPath('data.pwa_dismissal_duration_days', 45);

        // Validation rejects out of range (< 1 or > 365)
        $badRes = $this->actingAs($this->admin)
            ->putJson('/api/v1/admin/settings', [
                'group' => 'general',
                'settings' => [
                    'pwa_dismissal_duration_days' => 0,
                ],
            ]);
        $badRes->assertUnprocessable();
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

    public function test_taxonomies_bilingual_name_bn_resolution_and_farm_resource_integration(): void
    {
        // 1. Create a custom bilingual taxonomy item
        $resCreate = $this->actingAs($this->admin)->postJson('/api/v1/admin/taxonomies', [
            'type' => 'culture_type',
            'name' => 'Mud Crab Fattening',
            'name_bn' => 'শিলা কাঁকড়া হৃষ্টপুষ্টকরণ',
            'slug' => 'crab_fattening',
            'description' => 'Fattening wild mud crabs in pens or boxes',
            'is_active' => true,
        ]);
        $resCreate->assertCreated();
        $this->assertEquals('শিলা কাঁকড়া হৃষ্টপুষ্টকরণ', $resCreate->json('data.name_bn'));

        // 2. Active grouped endpoint delivers name_bn
        $resGrouped = $this->getJson('/api/v1/taxonomies');
        $resGrouped->assertOk();
        $cultureTypes = collect($resGrouped->json('data.culture_type') ?? []);
        $found = $cultureTypes->firstWhere('slug', 'crab_fattening');
        $this->assertNotNull($found);
        $this->assertEquals('Mud Crab Fattening', $found['name']);
        $this->assertEquals('শিলা কাঁকড়া হৃষ্টপুষ্টকরণ', $found['name_bn']);

        // 3. Update farm with this culture type
        $this->farm->main_culture_type = 'crab_fattening';
        $this->farm->farm_type = 'own';
        $this->farm->available_facilities = ['Electric', 'Aerator'];
        $this->farm->save();

        // 4. Test FarmResource via show endpoint
        $resFarm = $this->actingAs($this->farmer)->getJson("/api/v1/farms/{$this->farm->id}");
        $resFarm->assertOk();

        // Check English fields
        $this->assertEquals('crab_fattening', $resFarm->json('data.main_culture_type'));
        $this->assertEquals('own', $resFarm->json('data.farm_type'));

        // Check Bengali resolved fields
        $this->assertEquals('শিলা কাঁকড়া হৃষ্টপুষ্টকরণ', $resFarm->json('data.main_culture_type_bn'));
        $this->assertStringContainsString('নিজস্ব', $resFarm->json('data.farm_type_bn'));
        $facilitiesBn = $resFarm->json('data.available_facilities_bn');
        $this->assertTrue(collect($facilitiesBn)->contains(fn ($f) => str_contains($f, 'বিদ্যুৎ')));
    }

    public function test_taxonomy_seeder_runs_idempotently_with_bilingual_data(): void
    {
        // 1. Run TaxonomySeeder
        $this->seed(\Database\Seeders\TaxonomySeeder::class);

        // Verify canonical taxonomies exist with Bangla names and descriptions
        $this->assertDatabaseHas('taxonomies', [
            'type' => 'farm_type',
            'slug' => 'own',
            'name' => 'Own Land',
            'name_bn' => 'নিজস্ব জমি / ঘের',
        ]);

        $this->assertDatabaseHas('taxonomies', [
            'type' => 'culture_type',
            'slug' => 'bagda',
            'name_bn' => 'বাগদা চিংড়ি (Black Tiger)',
        ]);

        $this->assertDatabaseHas('taxonomies', [
            'type' => 'farming_system',
            'slug' => 'intensive',
            'name_bn' => 'নিবিড় চাষ পদ্ধতি (Intensive)',
        ]);

        $initialCount = Taxonomy::count();
        $this->assertGreaterThanOrEqual(40, $initialCount);

        // 2. Run TaxonomySeeder a second time (verify idempotency - no duplicate rows or errors)
        $this->seed(\Database\Seeders\TaxonomySeeder::class);
        $this->assertEquals($initialCount, Taxonomy::count());
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

    public function test_admin_can_update_language_defaults_and_public_and_role_locales_are_returned(): void
    {
        // 1. Initial public settings has defaults
        $resPublic = $this->getJson('/api/v1/settings/public');
        $resPublic->assertOk()
            ->assertJsonPath('data.default_public_locale', 'bn')
            ->assertJsonPath('data.default_role_locales.farmer', 'bn')
            ->assertJsonPath('data.default_role_locales.admin', 'en');

        // 2. Admin updates language settings
        $payload = [
            'group' => 'localization',
            'settings' => [
                'default_public_locale' => 'en',
                'default_locale_farmer' => 'bn',
                'default_locale_admin' => 'en',
                'default_locale_deo' => 'bn',
                'default_locale_vet' => 'en',
                'default_locale_consultant' => 'en',
            ],
        ];

        $resAdmin = $this->actingAs($this->admin)->putJson('/api/v1/admin/settings', $payload);
        $resAdmin->assertOk()
            ->assertJsonPath('success', true);

        // 3. Public settings reflects updated landing page and role defaults
        $resPublicUpdated = $this->getJson('/api/v1/settings/public');
        $resPublicUpdated->assertOk()
            ->assertJsonPath('data.default_public_locale', 'en')
            ->assertJsonPath('data.default_role_locales.vet', 'en')
            ->assertJsonPath('data.default_role_locales.consultant', 'en');

        // 4. Authenticated user profile returns computed role_default_locale
        $resMe = $this->actingAs($this->farmer)->getJson('/api/v1/me');
        $resMe->assertOk()
            ->assertJsonPath('data.role_default_locale', 'bn');

        $resAdminMe = $this->actingAs($this->admin)->getJson('/api/v1/me');
        $resAdminMe->assertOk()
            ->assertJsonPath('data.role_default_locale', 'en');
    }

    public function test_admin_can_update_guarded_branding_and_appearance_presets(): void
    {
        // 1. Initial public settings has default emerald branding and hind_siliguri font
        $resPublic = $this->getJson('/api/v1/settings/public');
        $resPublic->assertOk()
            ->assertJsonPath('data.brand_primary_color', 'emerald')
            ->assertJsonPath('data.brand_font_preset', 'hind_siliguri')
            ->assertJsonPath('data.default_appearance_mode', 'system');

        // 2. Admin updates branding to teal with noto_sans_bengali font
        $payload = [
            'group' => 'branding',
            'settings' => [
                'brand_primary_color' => 'teal',
                'brand_font_preset' => 'noto_sans_bengali',
                'default_appearance_mode' => 'light',
            ],
        ];

        $resAdmin = $this->actingAs($this->admin)->putJson('/api/v1/admin/settings', $payload);
        $resAdmin->assertOk()
            ->assertJsonPath('success', true);

        // 3. Public settings reflects updated branding
        $resPublicUpdated = $this->getJson('/api/v1/settings/public');
        $resPublicUpdated->assertOk()
            ->assertJsonPath('data.brand_primary_color', 'teal')
            ->assertJsonPath('data.brand_font_preset', 'noto_sans_bengali')
            ->assertJsonPath('data.default_appearance_mode', 'light');

        // 4. Invalid palette or font preset is rejected with 422
        $invalidPayload = [
            'group' => 'branding',
            'settings' => [
                'brand_primary_color' => 'hotpink',
                'brand_font_preset' => 'comic_sans',
            ],
        ];
        $resInvalid = $this->actingAs($this->admin)->putJson('/api/v1/admin/settings', $invalidPayload);
        $resInvalid->assertStatus(422);
    }
}
