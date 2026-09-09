<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class DocumentApiSecurityTest extends TestCase
{
    private const CLIENT_ID = 'test-client';

    private const API_KEY = 'test-api-key-123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'document_api.enabled' => true,
            'document_api.header' => 'X-API-Key',
            'document_api.default_rate_limit' => 2,
            'document_api.clients' => [
                self::CLIENT_ID => [
                    'key_hash' => hash('sha256', self::API_KEY),
                    'rate_limit_per_minute' => 2,
                ],
            ],
        ]);

        RateLimiter::clear('document-api-client:'.sha1(self::CLIENT_ID));
    }

    public function test_protected_api_rejects_missing_and_invalid_keys(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertStatus(401)
            ->assertJsonPath('code', 'missing_api_key');

        $this->withHeader('X-API-Key', 'wrong-key')
            ->getJson('/api/v1/meta')
            ->assertStatus(401)
            ->assertJsonPath('code', 'invalid_api_key');
    }

    public function test_valid_api_key_is_accepted_and_rate_limited_per_client(): void
    {
        $headers = ['X-API-Key' => self::API_KEY];

        $this->withHeaders($headers)
            ->getJson('/api/v1/meta')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '2')
            ->assertHeader('X-RateLimit-Remaining', '1');

        $this->withHeaders($headers)
            ->getJson('/api/v1/meta')
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->withHeaders($headers)
            ->getJson('/api/v1/meta')
            ->assertStatus(429)
            ->assertJsonPath('code', 'api_rate_limit_exceeded')
            ->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_cors_preflight_allows_a_configured_browser_origin_without_api_key(): void
    {
        config(['cors.allowed_origins' => ['https://app.example.com']]);

        $this->withHeaders([
            'Origin' => 'https://app.example.com',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'X-API-Key, Content-Type',
        ])->options('/api/v1/passport/scan')
            ->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
    }
}
