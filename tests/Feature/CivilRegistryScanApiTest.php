<?php

namespace Tests\Feature;

use App\DTO\OcrResult;
use App\Services\CivilRegistry\CivilRegistryOcrEngineInterface;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class CivilRegistryScanApiTest extends TestCase
{
    public function test_it_scans_a_residence_certificate_without_persisting_the_document(): void
    {
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
                        'تاريخ مولدها 1995-03-28',
                    ]),
                    confidence: 0.91,
                );
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
            ->assertJsonPath('data.scan.ocr_engine', 'fake-civil-registry-ocr')
            ->assertJsonPath('data.privacy.stores_document_images', false)
            ->assertJsonPath('data.privacy.temporary_files_deleted', true)
            ->assertJsonPath('meta.document_authenticity_verified', false);

        $directory = storage_path('app/private/passport-tmp');
        $remainingFiles = is_dir($directory)
            ? array_values(array_diff(scandir($directory) ?: [], ['.', '..']))
            : [];

        self::assertSame([], $remainingFiles);
    }
}
