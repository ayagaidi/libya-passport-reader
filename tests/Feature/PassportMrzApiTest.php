<?php

namespace Tests\Feature;

use Tests\TestCase;

class PassportMrzApiTest extends TestCase
{
    private const LINE_1 = 'P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<';
    private const LINE_2 = '1234567897LBY9501016F3001019<<<<<<<<<<<<<<02';

    public function test_it_parses_a_valid_td3_mrz(): void
    {
        $response = $this->postJson('/api/v1/passport/mrz/parse', [
            'line1' => self::LINE_1,
            'line2' => self::LINE_2,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.document_format', 'TD3')
            ->assertJsonPath('data.data.issuing_country', 'LBY')
            ->assertJsonPath('data.data.surname', 'ALGAIDI')
            ->assertJsonPath('data.data.given_names.0', 'AYA')
            ->assertJsonPath('data.data.is_libyan_passport', true)
            ->assertJsonPath('data.validation.mrz_valid', true)
            ->assertJsonPath('meta.stores_passport_data', false);
    }

    public function test_it_rejects_malformed_mrz_lines(): void
    {
        $this->postJson('/api/v1/passport/mrz/parse', [
            'line1' => 'too-short',
            'line2' => 'also-too-short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['line1', 'line2']);
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
