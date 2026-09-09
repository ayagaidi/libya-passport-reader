<?php

namespace App\Services\Passport\SmartScanner;

use App\DTO\SmartPassportImage;
use Symfony\Component\Process\Process;
use Throwable;

final class ImageMagickSmartPassportImageProcessor implements SmartPassportImageProcessorInterface
{
    public function __construct(private readonly PassportRegionGeometry $geometry) {}

    public function prepare(string $imagePath): SmartPassportImage
    {
        if (! config('passport.smart_scanner.enabled', true)) {
            return $this->fallback($imagePath, 'disabled');
        }

        $normalizedPath = $imagePath.'-smart-normalized.png';
        $mrzPath = $imagePath.'-smart-mrz.png';
        $visualPath = $imagePath.'-smart-visual.png';
        $temporaryPaths = [$normalizedPath, $mrzPath, $visualPath];

        try {
            if (! $this->preprocess($imagePath, $normalizedPath)) {
                return $this->fallbackAfterCleanup($imagePath, $temporaryPaths, 'imagemagick_unavailable');
            }

            $dimensions = @getimagesize($normalizedPath);

            if ($dimensions === false || ($dimensions[0] ?? 0) < 1 || ($dimensions[1] ?? 0) < 1) {
                return $this->fallbackAfterCleanup($imagePath, $temporaryPaths, 'dimension_detection_failed');
            }

            $regions = $this->geometry->calculate(
                width: (int) $dimensions[0],
                height: (int) $dimensions[1],
                mrzStartRatio: (float) config('passport.smart_scanner.mrz_start_ratio', 0.62),
                visualEndRatio: (float) config('passport.smart_scanner.visual_end_ratio', 0.78),
            );

            if (! $this->crop($normalizedPath, $mrzPath, $regions['mrz'], true)
                || ! $this->crop($normalizedPath, $visualPath, $regions['visual_zone'], false)) {
                return $this->fallbackAfterCleanup($imagePath, $temporaryPaths, 'region_crop_failed');
            }

            foreach ($temporaryPaths as $path) {
                @chmod($path, 0600);
            }

            return new SmartPassportImage(
                mrzImagePath: $mrzPath,
                visualZoneImagePath: $visualPath,
                temporaryPaths: $temporaryPaths,
                strategy: 'imagemagick_layout_regions',
                preprocessing: [
                    'auto_orient' => true,
                    'grayscale' => true,
                    'deskew' => true,
                    'contrast_stretch' => true,
                    'sharpen' => true,
                    'max_dimension' => (int) config('passport.smart_scanner.max_dimension', 2600),
                    'mrz_start_ratio' => (float) config('passport.smart_scanner.mrz_start_ratio', 0.62),
                    'visual_end_ratio' => (float) config('passport.smart_scanner.visual_end_ratio', 0.78),
                ],
            );
        } catch (Throwable) {
            return $this->fallbackAfterCleanup($imagePath, $temporaryPaths, 'processing_failed');
        }
    }

    private function preprocess(string $sourcePath, string $outputPath): bool
    {
        $maxDimension = max(1200, (int) config('passport.smart_scanner.max_dimension', 2600));

        return $this->run([
            $sourcePath,
            '-auto-orient',
            '-colorspace', 'Gray',
            '-deskew', (string) config('passport.smart_scanner.deskew_threshold', '40%'),
            '-contrast-stretch', (string) config('passport.smart_scanner.contrast_stretch', '1%x1%'),
            '-resize', $maxDimension.'x'.$maxDimension.'>',
            '-sharpen', '0x0.8',
            $outputPath,
        ]) && is_file($outputPath);
    }

    private function crop(string $sourcePath, string $outputPath, array $region, bool $enhanceMrz): bool
    {
        $crop = sprintf(
            '%dx%d+%d+%d',
            $region['width'],
            $region['height'],
            $region['x'],
            $region['y'],
        );

        $arguments = [
            $sourcePath,
            '-crop', $crop,
            '+repage',
        ];

        if ($enhanceMrz) {
            $arguments = array_merge($arguments, [
                '-contrast-stretch', '0.5%x0.5%',
                '-sharpen', '0x1',
                '-resize', '3000x>',
            ]);
        }

        $arguments[] = $outputPath;

        return $this->run($arguments) && is_file($outputPath);
    }

    private function run(array $arguments): bool
    {
        $binary = (string) config('passport.smart_scanner.imagemagick_binary', 'magick');
        $process = new Process(array_merge([$binary], $arguments));
        $process->setTimeout((float) config('passport.smart_scanner.timeout', 15));

        try {
            $process->run();
        } catch (Throwable) {
            return false;
        }

        return $process->isSuccessful();
    }

    private function fallbackAfterCleanup(string $imagePath, array $temporaryPaths, string $reason): SmartPassportImage
    {
        foreach ($temporaryPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return $this->fallback($imagePath, $reason);
    }

    private function fallback(string $imagePath, string $reason): SmartPassportImage
    {
        return new SmartPassportImage(
            mrzImagePath: $imagePath,
            visualZoneImagePath: $imagePath,
            strategy: 'full_image_fallback',
            preprocessing: [
                'applied' => false,
                'reason' => $reason,
            ],
        );
    }
}
