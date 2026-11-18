<?php

namespace Tests\Feature;

use App\Models\LabTestParameter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminLabTestParameterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;
    protected User $farmer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'data_entry_operator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'farmer', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->deo = User::factory()->create();
        $this->deo->assignRole('data_entry_operator');

        $this->farmer = User::factory()->create();
        $this->farmer->assignRole('farmer');
    }

    public function test_admin_and_deo_can_list_lab_test_parameters(): void
    {
        LabTestParameter::create([
            'name' => 'Hydrogen Sulfide (H2S)',
            'name_bn' => 'হাইড্রোজেন সালফাইড (H2S)',
            'category' => 'water',
            'default_unit' => 'ppm',
            'default_range' => '< 0.01',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->deo)
            ->getJson('/api/v1/admin/settings/lab-test-parameters');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data');
        expect(count($items))->toBeGreaterThanOrEqual(1);
        expect(collect($items)->firstWhere('name', 'Hydrogen Sulfide (H2S)')['default_range'])->toBe('< 0.01');
    }

    public function test_admin_can_create_a_new_lab_test_parameter(): void
    {
        $payload = [
            'name' => 'Vibrio Colony Count',
            'name_bn' => 'ভিব্রিও ব্যাকটেরিয়ার ঘনত্ব',
            'category' => 'microbiology',
            'default_unit' => 'CFU/mL',
            'default_range' => '< 1000',
            'sort_order' => 15,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/settings/lab-test-parameters', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Vibrio Colony Count')
            ->assertJsonPath('data.category', 'microbiology')
            ->assertJsonPath('data.default_unit', 'CFU/mL')
            ->assertJsonPath('data.default_range', '< 1000');

        $this->assertDatabaseHas('lab_test_parameters', [
            'name' => 'Vibrio Colony Count',
            'category' => 'microbiology',
            'default_unit' => 'CFU/mL',
        ]);
    }

    public function test_admin_can_update_a_lab_test_parameter(): void
    {
        $param = LabTestParameter::create([
            'name' => 'Old Alkalinity',
            'category' => 'water',
            'default_unit' => 'mg/L',
            'default_range' => '50 - 100',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/settings/lab-test-parameters/{$param->id}", [
                'name' => 'Total Alkalinity (CaCO3)',
                'name_bn' => 'মোট ক্ষারত্ব',
                'category' => 'water',
                'default_unit' => 'mg/L',
                'default_range' => '80 - 150',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Total Alkalinity (CaCO3)')
            ->assertJsonPath('data.default_range', '80 - 150');

        $this->assertDatabaseHas('lab_test_parameters', [
            'id' => $param->id,
            'name' => 'Total Alkalinity (CaCO3)',
            'default_range' => '80 - 150',
        ]);
    }

    public function test_admin_can_toggle_lab_test_parameter_active_status(): void
    {
        $param = LabTestParameter::create([
            'name' => 'Turbidity',
            'category' => 'water',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/settings/lab-test-parameters/{$param->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect($param->fresh()->is_active)->toBeFalse();
    }

    public function test_admin_can_delete_a_lab_test_parameter(): void
    {
        $param = LabTestParameter::create([
            'name' => 'Obsolete Test',
            'category' => 'other',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/settings/lab-test-parameters/{$param->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('lab_test_parameters', [
            'id' => $param->id,
        ]);
    }

    public function test_farmer_cannot_modify_lab_test_parameters(): void
    {
        $response = $this->actingAs($this->farmer)
            ->postJson('/api/v1/admin/settings/lab-test-parameters', [
                'name' => 'Hacker Test',
                'category' => 'water',
            ]);

        $response->assertForbidden();
    }
}
