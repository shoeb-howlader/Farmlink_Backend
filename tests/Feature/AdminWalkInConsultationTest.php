<?php

namespace Tests\Feature;

use App\Models\ConsultantRecord;
use App\Models\Farm;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VetRecord;
use App\Models\VisitTestResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminWalkInConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;
    protected User $farmer;
    protected Farm $farm;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'consultant', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'veterinary_doctor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['name' => 'Admin Staff']);
        $this->admin->assignRole('admin');

        $this->deo = User::factory()->create(['name' => 'DEO Staff']);
        $this->deo->assignRole('data_entry_operator');

        $this->farmer = User::factory()->create([
            'name' => 'Rahim Ali',
            'phone' => '01711002233',
            'district' => 'Satkhira',
        ]);
        $this->farmer->assignRole('farmer');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_name' => 'Rahim Fish Pond',
            'district' => 'Satkhira',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'OxyFlow 500g',
            'price' => 350.00,
            'stock' => 50,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_record_walk_in_consultant_record_with_tests_and_prescriptions(): void
    {
        $payload = [
            'farmer_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'type' => 'consultant',
            'findings' => 'Water turned blackish; fish gasping at surface.',
            'treatment' => 'Apply oxygen tablets and liming immediately.',
            'recommendation' => 'Maintain aeration and test water daily.',
            'next_follow_up' => now()->addDays(3)->toDateString(),
            'test_results' => [
                [
                    'parameter' => 'Dissolved Oxygen',
                    'value' => '3.2',
                    'unit' => 'mg/L',
                    'reference_range' => '5.0 - 8.0',
                    'flag' => 'low',
                ],
                [
                    'parameter' => 'pH',
                    'value' => '8.4',
                    'unit' => '',
                    'reference_range' => '6.5 - 8.5',
                    'flag' => 'normal',
                ],
            ],
            'prescription_items' => [
                [
                    'product_id' => $this->product->id,
                    'medicine_name' => 'OxyFlow 500g',
                    'dosage' => '25 g / decimal',
                    'frequency' => 'Twice daily',
                    'duration' => '3 days',
                    'instructions' => 'Spread evenly in morning',
                    'quantity' => 2,
                ],
            ],
            'test_fee_amount' => 100.00,
            'send_sms' => false,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/pos/walk-in-consultation', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'record_type' => 'consultant',
                    'test_results_count' => 2,
                ],
            ]);

        // Assert database records
        $this->assertDatabaseHas('service_requests', [
            'farmer_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'source_channel' => 'walk_in',
            'status' => 'completed',
            'fulfilled_record_type' => ConsultantRecord::class,
        ]);

        $this->assertDatabaseHas('consultant_records', [
            'farm_id' => $this->farm->id,
            'consultant_id' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('visit_test_results', [
            'parameter' => 'Dissolved Oxygen',
            'value' => '3.2',
            'flag' => 'low',
        ]);

        $this->assertDatabaseHas('prescription_items', [
            'product_id' => $this->product->id,
            'medicine_name' => 'OxyFlow 500g',
        ]);

        // Assert cart items prepared for POS
        $data = $response->json('data');
        $this->assertNotEmpty($data['cart_items']);
        $this->assertCount(2, $data['cart_items']); // Test fee product + OxyFlow
        $this->assertNotNull($data['pdf_url']);
    }

    public function test_deo_can_record_walk_in_vet_record(): void
    {
        $payload = [
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'findings' => 'Gill rot and fin erosion observed on carp.',
            'treatment' => 'Potassium permanganate dip and antibiotic feed.',
            'send_sms' => false,
        ];

        $response = $this->actingAs($this->deo)->postJson('/api/v1/admin/pos/walk-in-consultation', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'record_type' => 'vet',
                ],
            ]);

        $this->assertDatabaseHas('vet_records', [
            'farm_id' => $this->farm->id,
            'vet_id' => $this->deo->id,
            'findings' => 'Gill rot and fin erosion observed on carp.',
        ]);
    }

    public function test_blacklisted_farmer_cannot_receive_walk_in_consultation(): void
    {
        $this->farmer->update([
            'is_blacklisted' => true,
            'blacklist_reason' => 'Suspected pesticide violation',
        ]);

        $payload = [
            'farmer_id' => $this->farmer->id,
            'type' => 'consultant',
            'findings' => 'General checkup',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/pos/walk-in-consultation', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['farmer_id']);
    }

    public function test_farmer_cannot_call_walk_in_consultation_endpoint(): void
    {
        $payload = [
            'farmer_id' => $this->farmer->id,
            'type' => 'consultant',
        ];

        $response = $this->actingAs($this->farmer)->postJson('/api/v1/admin/pos/walk-in-consultation', $payload);

        $response->assertStatus(403);
    }

    public function test_admin_can_fetch_walk_in_history_for_farmer(): void
    {
        // Create past record
        $consultantRecord = ConsultantRecord::create([
            'farm_id' => $this->farm->id,
            'consultant_id' => $this->admin->id,
            'visit_date' => now()->subDays(5)->toDateString(),
            'recommendation' => 'Past recommendation',
        ]);

        $consultantRecord->testResults()->create([
            'parameter' => 'pH',
            'value' => '7.8',
            'flag' => 'normal',
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/pos/walk-in-history/{$this->farmer->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Past recommendation', $response->json('data.0.recommendation'));
        $this->assertEquals('7.8', $response->json('data.0.test_results.0.value'));
    }
}
