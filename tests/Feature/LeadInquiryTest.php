<?php

namespace Tests\Feature;

use App\Models\LeadInquiry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadInquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_can_submit_lead_inquiry_with_real_farmer_values(): void
    {
        $payload = [
            'name' => 'Md. Hasan',
            'phone' => '01712345678',
            'email' => 'hasan@example.com',
            'topic' => '🐟 Fingerlings & Seeds',
            'message' => 'I have a 3-acre fish farm in Mymensingh. Looking for veterinary pond visit pricing and fingerlings.',
            'source' => 'homepage_widget',
        ];

        $response = $this->postJson('/api/v1/lead-inquiries', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Md. Hasan',
                    'phone' => '01712345678',
                    'topic' => '🐟 Fingerlings & Seeds',
                    'source' => 'homepage_widget',
                    'status' => 'new',
                ],
            ]);

        // Exact values stored in dedicated lead_inquiries table - no truncation or placeholder substitution
        $this->assertDatabaseHas('lead_inquiries', [
            'name' => 'Md. Hasan',
            'phone' => '01712345678',
            'email' => 'hasan@example.com',
            'topic' => '🐟 Fingerlings & Seeds',
            'source' => 'homepage_widget',
            'status' => 'new',
        ]);

        // Assert notification created for general bell (visually distinct lead alert)
        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'lead.inquiry',
            'title' => 'Website Lead: Md. Hasan',
        ]);

        // Assert guest inquiry is NEVER stored into conversations or messages tables
        $this->assertDatabaseEmpty('conversations');
        $this->assertDatabaseEmpty('messages');
    }

    public function test_lead_inquiry_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lead-inquiries', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone', 'message']);
    }

    public function test_admin_can_view_lead_inquiries_list_with_counts(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');

        LeadInquiry::create([
            'name' => 'Test Farmer',
            'phone' => '01899887766',
            'topic' => '🧪 Water Quality Test',
            'message' => 'Need water quality test kit.',
            'source' => 'homepage_widget',
            'status' => 'new',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/lead-inquiries');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment(['name' => 'Test Farmer'])
            ->assertJsonStructure([
                'counts' => ['all', 'new', 'contacted', 'converted'],
            ]);
    }

    public function test_admin_can_update_lead_inquiry_status_to_contacted_and_converted(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('admin');

        $inquiry = LeadInquiry::create([
            'name' => 'Md. Hasan',
            'phone' => '01712345678',
            'message' => 'Consultation inquiry',
            'status' => 'new',
        ]);

        Sanctum::actingAs($admin);

        // Mark as contacted
        $res1 = $this->patchJson("/api/v1/admin/lead-inquiries/{$inquiry->id}/status", [
            'status' => 'contacted',
            'notes' => 'Called via WhatsApp, arranged pond doctor visit.',
        ]);

        $res1->assertStatus(200)
            ->assertJsonPath('data.status', 'contacted');

        $this->assertDatabaseHas('lead_inquiries', [
            'id' => $inquiry->id,
            'status' => 'contacted',
            'notes' => 'Called via WhatsApp, arranged pond doctor visit.',
        ]);

        // Mark as converted
        $res2 = $this->patchJson("/api/v1/admin/lead-inquiries/{$inquiry->id}/status", [
            'status' => 'converted',
        ]);

        $res2->assertStatus(200)
            ->assertJsonPath('data.status', 'converted');

        $this->assertDatabaseHas('lead_inquiries', [
            'id' => $inquiry->id,
            'status' => 'converted',
        ]);
    }
}
