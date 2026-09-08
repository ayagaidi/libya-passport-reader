<?php

namespace Tests\Feature;

use App\DTO\OcrResult;
use App\Services\Passport\Ocr\OcrEngineInterface;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class PassportScanApiTest extends TestCase
{
    public const LINE_1 = 'P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<';

    public const LINE_2 = '1234567897LBY9501016F3001019<<<<<<<<<<<<<<02';

    public function test_it_scans_an_uploaded_image_without_persisting_the_document(): void
    {
        $this->app->instance(OcrEngineInterface::class, new class implements OcrEngineInterface
        {
            public function read(string $imagePath): OcrResult
            {
                return new OcrResult(
                    engine: 'fake-test-engine',
                    text: PassportScanApiTest::LINE_1."\n".PassportScanApiTest::LINE_2,
                    confidence: 99.5,
                );
            }
        });

        $temporaryImage = tempnam(sys_get_temp_dir(), 'passport-reader-test-');
        self::assertNotFalse($temporaryImage);

        file_put_contents(
            $temporaryImage,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );

        $file = new UploadedFile(
            $temporaryImage,
            'synthetic-passport.png',
            'image/png',
            null,
            true,
        );

        $response = $this->post('/api/v1/passport/scan', [
            'passport' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.passport.data.issuing_country', 'LBY')
            ->assertJsonPath('data.passport.data.surname', 'ALGAIDI')
            ->assertJsonPath('data.scan.mrz_detected', true)
            ->assertJsonPath('data.scan.ocr_engine', 'fake-test-engine')
            ->assertJsonPath('data.privacy.stores_passport_images', false)
            ->assertJsonPath('data.privacy.temporary_files_deleted', true)
            ->assertJsonPath('meta.document_authenticity_verified', false);

        $directory = storage_path('app/private/passport-tmp');
        $remainingFiles = is_dir($directory)
            ? array_values(array_diff(scandir($directory) ?: [], ['.', '..']))
            : [];

        self::assertSame([], $remainingFiles);
    }
}
