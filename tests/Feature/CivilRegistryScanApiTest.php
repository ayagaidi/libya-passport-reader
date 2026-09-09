<?php

namespace Tests\Feature;

use App\DTO\OcrResult;
use App\Services\CivilRegistry\CivilRegistryOcrEngineInterface;
use App\Services\CivilRegistry\CivilRegistryVisualVerifierInterface;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class CivilRegistryScanApiTest extends TestCase
{
    public function test_it_scans_and_scores_a_residence_certificate_without_persisting_the_document(): void
    {
        config(['passport.civil_registry.verification_enabled' => true]);

        $this->app->instance(CivilRegistryOcrEngineInterface::class, new class implements CivilRegistryOcrEngineInterface
        {
            public function read(string $imagePath): OcrResult
            {
                return new OcrResult(
                    engine: 'fake-civil-registry-ocr',
                    text: implode("\n", [
                        'مصلحة الأحوال المدنية',
                        'شهادة الإقامة',
                        'الرقم الوطني 123456789012',
                        'مكتب السجل المدني سوق الجمعة',
                        'رقم قيد العائلة 106101',
                        'تاريخ مولدها 1995-03-28',
                    ]),
                    confidence: 0.91,
                );
            }
        });

        $this->app->instance(CivilRegistryVisualVerifierInterface::class, new class implements CivilRegistryVisualVerifierInterface
        {
            public function verify(string $imagePath, string $documentType): array
            {
                return [
                    'status' => 'processed',
                    'qr' => [
                        'detected' => true,
                        'decoded' => true,
                        'payload_format' => 'civil_registry_check_number',
                        'structure_valid' => true,
                        'check_number' => 'eed2f8f6-c081-cc97-4c22-b03a61ef8f86',
                        'expected_position' => 'top_right',
                        'position_consistent' => true,
                        'issuer_lookup_performed' => false,
                    ],
                    'seals' => [
                        'detected' => true,
                        'candidate_count' => 1,
                        'expected_locations' => ['lower_center'],
                        'expected_location_match' => true,
                        'candidates' => [],
                    ],
                    'visual_signal_score' => 1.0,
                    'authenticity_verified' => false,
                ];
            }
        });

        $temporaryImage = tempnam(sys_get_temp_dir(), 'civil-registry-test-');
        self::assertNotFalse($temporaryImage);

        file_put_contents(
            $temporaryImage,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );

        $file = new UploadedFile(
            $temporaryImage,
            'synthetic-civil-registry.png',
            'image/png',
            null,
            true,
        );

        $response = $this->post('/api/v1/civil-registry/scan', [
            'document' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.document.type', 'residence_certificate')
            ->assertJsonPath('data.document.fields.national_number', '123456789012')
            ->assertJsonPath('data.verification.status', 'signals_consistent')
            ->assertJsonPath('data.verification.qr.check_number', 'eed2f8f6-c081-cc97-4c22-b03a61ef8f86')
            ->assertJsonPath('data.verification.seals.detected', true)
            ->assertJsonPath('data.verification.authenticity_verified', false)
            ->assertJsonPath('data.scan.ocr_engine', 'fake-civil-registry-ocr')
            ->assertJsonPath('data.scan.ocr_scope', 'full_prepared_document')
            ->assertJsonPath('data.privacy.stores_document_images', false)
            ->assertJsonPath('data.privacy.returns_raw_qr_payload', false)
            ->assertJsonPath('data.privacy.temporary_files_deleted', true)
            ->assertJsonPath('meta.document_authenticity_verified', false);

        $directory = storage_path('app/private/passport-tmp');
        $remainingFiles = is_dir($directory)
            ? array_values(array_diff(scandir($directory) ?: [], ['.', '..']))
            : [];

        self::assertSame([], $remainingFiles);
    }
}
