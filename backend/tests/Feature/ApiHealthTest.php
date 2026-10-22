<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_health_is_public_json_and_does_not_start_a_session(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'service' => 'spectacles-api'])
            ->assertHeaderMissing('Set-Cookie');
    }

    public function test_configured_frontend_can_read_the_api_without_credentials(): void
    {
        config(['cors.allowed_origins' => ['https://app.example.com']]);

        $this->withHeader('Origin', 'https://app.example.com')
            ->getJson('/api/health')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_other_origins_do_not_receive_cors_permission(): void
    {
        config(['cors.allowed_origins' => ['https://app.example.com']]);

        $this->withHeader('Origin', 'https://other.example.com')
            ->getJson('/api/health')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
    }

    public function test_cors_preflight_allows_the_health_request(): void
    {
        config(['cors.allowed_origins' => ['https://app.example.com']]);

        $this->withHeaders([
            'Origin' => 'https://app.example.com',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Accept',
        ])->options('/api/health')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
    }

    public function test_unknown_api_routes_return_json(): void
    {
        $this->get('/api/missing')->assertNotFound()->assertJsonStructure(['message']);
    }
}
