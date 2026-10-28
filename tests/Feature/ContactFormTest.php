<?php

namespace Tests\Feature;

use App\Models\LeadInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_submission_succeeds_and_saves_to_database(): void
    {
        $payload = [
            'name' => 'Kalam Chowdhury',
            'email' => 'kalam@farmlink-test.com',
            'subject' => 'Shrimp Feed Inquiry for Satkhira Farm',
            'message' => 'Hello FarmLink, I have 3 shrimp ponds in Satkhira and want to inquire about bulk delivery of probiotic feed.',
        ];

        $response = $this->postJson('/api/contact', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Kalam Chowdhury',
                    'status' => 'new',
                    'source' => 'contact_page',
                ],
            ])
            ->assertJsonStructure(['success', 'message', 'data']);

        $this->assertDatabaseHas('lead_inquiries', [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'topic' => $payload['subject'],
            'message' => $payload['message'],
            'source' => 'contact_page',
            'status' => 'new',
        ]);

        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'lead.inquiry',
            'title' => 'New Contact Message: Kalam Chowdhury',
        ]);
    }

    public function test_contact_submission_also_works_on_v1_route(): void
    {
        $payload = [
            'name' => 'Rahim Uddin',
            'email' => 'rahim@farmlink-test.com',
            'subject' => 'Veterinary Consultation Booking',
            'message' => 'Need consultation for water pH fluctuations in pond #2.',
        ];

        $response = $this->postJson('/api/v1/contact', $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('lead_inquiries', [
            'name' => 'Rahim Uddin',
            'email' => 'rahim@farmlink-test.com',
            'topic' => 'Veterinary Consultation Booking',
            'source' => 'contact_page',
        ]);
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $response = $this->postJson('/api/contact', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_contact_form_validates_email_format(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Valid Name',
            'email' => 'not-an-email',
            'subject' => 'Valid Subject',
            'message' => 'Valid message with sufficient length.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_contact_form_validates_minimum_lengths(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'A',
            'email' => 'test@example.com',
            'subject' => 'Hi',
            'message' => 'Short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'subject', 'message']);
    }

    public function test_honeypot_field_tripped_by_bot_rejects_storage(): void
    {
        $payload = [
            'name' => 'Spam Bot',
            'email' => 'spambot@example.com',
            'subject' => 'Make Million Dollars Fast',
            'message' => 'Click this suspicious link right now!',
            'website' => 'http://spam-link.com',
        ];

        $response = $this->postJson('/api/contact', $payload);

        $this->assertTrue(in_array($response->status(), [200, 422], true));
        $this->assertDatabaseMissing('lead_inquiries', [
            'email' => 'spambot@example.com',
        ]);
    }

    public function test_contact_form_rate_limits_after_five_requests(): void
    {
        $payload = [
            'name' => 'Rapid Submitter',
            'email' => 'rapid@example.com',
            'subject' => 'Rapid Request',
            'message' => 'Valid message text with sufficient characters.',
        ];

        // Send 5 valid requests
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/contact', $payload);
            $response->assertStatus(200);
        }

        // 6th request within 1 minute must return 429 Too Many Requests
        $response = $this->postJson('/api/contact', $payload);
        $response->assertStatus(429);
    }
}
