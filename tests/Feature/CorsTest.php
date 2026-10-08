<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_farmlinkcare_origin_receives_cors_header_on_get_request(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://farmlinkcare.com',
        ])->getJson('/api/v1/settings/public');

        $response->assertHeader('Access-Control-Allow-Origin', 'https://farmlinkcare.com');
    }

    public function test_farmlinkcare_origin_receives_cors_header_on_options_preflight(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://farmlinkcare.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,accept,authorization',
        ])->options('/api/v1/login');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://farmlinkcare.com');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_farmlinkcare_subdomain_is_allowed_via_pattern(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://app.farmlinkcare.com',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/login');

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', 'https://app.farmlinkcare.com');
    }

    public function test_unauthorized_origin_does_not_receive_cors_header(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil-hacker-site.com',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/login');

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }
}
