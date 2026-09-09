<?php

namespace App\Services\Passport\Vision;

use App\DTO\VisionPassportImage;
use Symfony\Component\Process\Process;
use Throwable;

final class OpenCvVisionPassportImageProcessor implements VisionPassportImageProcessorInterface
{
    public function prepare(string $imagePath): VisionPassportImage
    {
        if (! config('passport.vision.enabled', true)) {
            return $this->fallback($imagePath, 'disabled');
        }

        $outputPath = $imagePath.'-vision-corrected.png';
        $scriptPath = base_path('tools/passport_vision.py');
        $python = (string) config('passport.vision.python_binary', 'python3');
        $process = new Process([
            $python,
            $scriptPath,
            $imagePath,
            $outputPath,
            '--min-area-ratio', (string) config('passport.vision.min_document_area_ratio', 0.20),
            '--blur-warning', (string) config('passport.vision.blur_warning_threshold', 75),
            '--blur-reject', (string) config('passport.vision.blur_reject_threshold', 35),
            '--glare-warning', (string) config('passport.vision.glare_warning_ratio', 0.18),
            '--glare-reject', (string) config('passport.vision.glare_reject_ratio', 0.35),
            '--overexposure-warning', (string) config('passport.vision.overexposure_warning_ratio', 0.80),
            '--overexposure-reject', (string) config('passport.vision.overexposure_reject_ratio', 0.95),
            '--max-dimension', (string) config('passport.vision.max_detection_dimension', 1400),
        ]);
        $process->setTimeout((float) config('passport.vision.timeout', 12));

        try {
            $process->run();
        } catch (Throwable) {
            return $this->fallbackAfterCleanup($imagePath, $outputPath, 'vision_process_unavailable');
        }

        $payload = json_decode(trim($process->getOutput()), true);

        if (! is_array($payload)) {
            return $this->fallbackAfterCleanup($imagePath, $outputPath, 'invalid_vision_response');
        }

        if (($payload['status'] ?? null) === 'unavailable') {
            return $this->fallbackAfterCleanup(
                $imagePath,
                $outputPath,
                (string) ($payload['reason'] ?? 'opencv_unavailable'),
            );
        }

        if (! $process->isSuccessful() && ! in_array(($payload['status'] ?? null), ['not_detected', 'cropped'], true)) {
            return $this->fallbackAfterCleanup($imagePath, $outputPath, 'vision_processing_failed');
        }

        $status = (string) ($payload['status'] ?? 'unknown');
        $perspectiveCorrected = (bool) ($payload['perspective_corrected'] ?? false);
        $documentDetected = (bool) ($payload['document_detected'] ?? false);
        $contentCropped = $status === 'cropped' && is_file($outputPath);
        $useProcessedImage = ($perspectiveCorrected || $contentCropped) && is_file($outputPath);
        $temporaryPaths = $useProcessedImage ? [$outputPath] : [];

        if ($useProcessedImage) {
            @chmod($outputPath, 0600);
        } elseif (is_file($outputPath)) {
            @unlink($outputPath);
        }

        $strategy = match (true) {
            $perspectiveCorrected => 'opencv_document_corners',
            $contentCropped => 'opencv_content_crop',
            default => 'opencv_quality_only',
        };

        return new VisionPassportImage(
            imagePath: $useProcessedImage ? $outputPath : $imagePath,
            temporaryPaths: $temporaryPaths,
            strategy: $strategy,
            documentDetected: $documentDetected,
            perspectiveCorrected: $perspectiveCorrected,
            quality: $this->quality($payload['quality'] ?? []),
            diagnostics: [
                'status' => $status,
                'detection_method' => $payload['detection_method'] ?? null,
                'document_area_ratio' => isset($payload['document_area_ratio'])
                    ? round((float) $payload['document_area_ratio'], 4)
                    : null,
                'quality_scope' => $payload['quality_scope'] ?? null,
            ],
        );
    }

    private function quality(mixed $quality): array
    {
        if (! is_array($quality)) {
            return [
                'status' => 'not_evaluated',
                'reasons' => [],
            ];
        }

        return [
            'status' => in_array(($quality['status'] ?? null), ['accepted', 'warning', 'rejected'], true)
                ? $quality['status']
                : 'not_evaluated',
            'reasons' => array_values(array_filter(
                is_array($quality['reasons'] ?? null) ? $quality['reasons'] : [],
                static fn (mixed $reason): bool => in_array($reason, ['blur', 'glare', 'overexposure'], true),
            )),
            'blur_score' => isset($quality['blur_score']) ? round((float) $quality['blur_score'], 2) : null,
            'glare_ratio' => isset($quality['glare_ratio']) ? round((float) $quality['glare_ratio'], 4) : null,
            'overexposure_ratio' => isset($quality['overexposure_ratio'])
                ? round((float) $quality['overexposure_ratio'], 4)
                : null,
        ];
    }

    private function fallbackAfterCleanup(string $imagePath, string $outputPath, string $reason): VisionPassportImage
    {
        if (is_file($outputPath)) {
            @unlink($outputPath);
        }

        return $this->fallback($imagePath, $reason);
    }

    private function fallback(string $imagePath, string $reason): VisionPassportImage
    {
        return new VisionPassportImage(
            imagePath: $imagePath,
            strategy: 'vision_fallback',
            quality: [
                'status' => 'not_evaluated',
                'reasons' => [],
            ],
            diagnostics: [
                'status' => 'unavailable',
                'reason' => $reason,
            ],
        );
    }
}
