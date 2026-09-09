<?php

namespace Tests\Feature;

use App\DTO\VisionPassportImage;
use App\Services\Passport\Vision\VisionPassportImageProcessorInterface;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class PassportVisionQualityApiTest extends TestCase
{
    public function test_it_rejects_severely_low_quality_images_without_persisting_the_upload(): void
    {
        config()->set('passport.vision.reject_low_quality', true);
        $this->app->instance(VisionPassportImageProcessorInterface::class, new class implements VisionPassportImageProcessorInterface
        {
            public function prepare(string $imagePath): VisionPassportImage
            {
                return new VisionPassportImage(
                    imagePath: $imagePath,
                    strategy: 'fake-vision-test',
                    quality: [
                        'status' => 'rejected',
                        'reasons' => ['blur'],
                        'blur_score' => 10.0,
                        'glare_ratio' => 0.01,
                    ],
                );
            }
        });

        $temporaryImage = tempnam(sys_get_temp_dir(), 'passport-vision-test-');
        self::assertNotFalse($temporaryImage);
        file_put_contents(
            $temporaryImage,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );
        $file = new UploadedFile($temporaryImage, 'synthetic-passport.png', 'image/png', null, true);

        $response = $this->post('/api/v1/passport/scan', [
            'passport' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'low_image_quality')
            ->assertJsonPath('quality.status', 'rejected')
            ->assertJsonPath('quality.reasons.0', 'blur');

        $directory = storage_path('app/private/passport-tmp');
        $remainingFiles = is_dir($directory)
            ? array_values(array_diff(scandir($directory) ?: [], ['.', '..']))
            : [];

        self::assertSame([], $remainingFiles);
    }
}
