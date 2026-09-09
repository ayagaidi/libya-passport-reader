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
        $visualPath = $imagePath.'-smart-visual.png';
        $temporaryPaths = [$normalizedPath, $visualPath];

        try {
            if (! $this->preprocess($imagePath, $normalizedPath)) {
                return $this->fallbackAfterCleanup($imagePath, $temporaryPaths, 'imagemagick_unavailable');
            }

            $dimensions = @getimagesize($normalizedPath);

            if ($dimensions === false || ($dimensions[0] ?? 0) < 1 || ($dimensions[1] ?? 0) < 1) {
                return $this->fallbackAfterCleanup($imagePath, $temporaryPaths, 'dimension_detection_failed');
            }

            $width = (int) $dimensions[0];
            $height = (int) $dimensions[1];
            $visualEndRatio = (float) config('passport.smart_scanner.visual_end_ratio', 0.78);
            $primaryMrzStartRatio = (float) config('passport.smart_scanner.mrz_start_ratio', 0.62);
            $candidateRatios = $this->candidateRatios($primaryMrzStartRatio);
            $mrzCandidatePaths = [];
            $adaptiveMrzCandidatePaths = [];

            foreach ($candidateRatios as $index => $ratio) {
                $regions = $this->geometry->calculate(
                    width: $width,
                    height: $height,
                    mrzStartRatio: $ratio,
                    visualEndRatio: $visualEndRatio,
                );
                $candidatePath = $imagePath.'-smart-mrz-'.$index.'.png';
                $temporaryPaths[] = $candidatePath;

                if ($this->crop($normalizedPath, $candidatePath, $regions['mrz'], true)) {
                    $mrzCandidatePaths[] = $candidatePath;
                }

                $adaptiveCandidatePath = $imagePath.'-smart-mrz-adaptive-'.$index.'.png';
                $temporaryPaths[] = $adaptiveCandidatePath;

                if ($this->adaptiveMrzBand($imagePath, $adaptiveCandidatePath, $ratio)) {
                    $adaptiveMrzCandidatePaths[] = $adaptiveCandidatePath;
                }
            }

            $primaryRegions = $this->geometry->calculate(
                width: $width,
                height: $height,
                mrzStartRatio: $primaryMrzStartRatio,
                visualEndRatio: $visualEndRatio,
            );
            $allRegionCandidates = array_values(array_unique(array_merge(
                $mrzCandidatePaths,
                $adaptiveMrzCandidatePaths,
            )));

            if ($allRegionCandidates === [] || ! $this->crop($normalizedPath, $visualPath, $primaryRegions['visual_zone'], false)) {
                return $this->fallbackAfterCleanup($imagePath, $temporaryPaths, 'region_crop_failed');
            }

            foreach ($temporaryPaths as $path) {
                if (is_file($path)) {
                    @chmod($path, 0600);
                }
            }

            $adaptiveCandidates = array_values(array_unique(array_merge(
                $allRegionCandidates,
                [$normalizedPath, $imagePath],
            )));

            return new SmartPassportImage(
                mrzImagePath: $allRegionCandidates[0],
                visualZoneImagePath: $visualPath,
                temporaryPaths: $temporaryPaths,
                strategy: 'imagemagick_adaptive_regions',
                preprocessing: [
                    'auto_orient' => true,
                    'grayscale' => true,
                    'deskew' => true,
                    'contrast_stretch' => true,
                    'sharpen' => true,
                    'adaptive_local_threshold' => $adaptiveMrzCandidatePaths !== [],
                    'max_dimension' => (int) config('passport.smart_scanner.max_dimension', 2600),
                    'mrz_candidate_start_ratios' => $candidateRatios,
                    'mrz_adaptive_candidate_count' => count($adaptiveMrzCandidatePaths),
                    'visual_end_ratio' => $visualEndRatio,
                ],
                mrzCandidatePaths: $adaptiveCandidates,
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

    private function adaptiveMrzBand(string $sourcePath, string $outputPath, float $startRatio): bool
    {
        if (! config('passport.smart_scanner.mrz_adaptive_threshold_enabled', true)) {
            return false;
        }

        $python = (string) config('passport.vision.python_binary', 'python3');
        $scriptPath = base_path('tools/mrz_band_preprocess.py');
        $process = new Process([
            $python,
            $scriptPath,
            $sourcePath,
            $outputPath,
            '--start-ratio', (string) $startRatio,
            '--target-width', (string) config('passport.smart_scanner.mrz_adaptive_target_width', 3200),
            '--block-size', (string) config('passport.smart_scanner.mrz_adaptive_block_size', 41),
            '--constant', (string) config('passport.smart_scanner.mrz_adaptive_constant', 15),
        ]);
        $process->setTimeout((float) config('passport.smart_scanner.timeout', 15));

        try {
            $process->run();
        } catch (Throwable) {
            return false;
        }

        return $process->isSuccessful() && is_file($outputPath);
    }

    private function candidateRatios(float $primary): array
    {
        $configured = config('passport.smart_scanner.mrz_candidate_start_ratios', [0.54, 0.60, 0.66]);
        $ratios = is_array($configured) ? $configured : [$primary];
        $ratios[] = $primary;
        $ratios = array_map(
            static fn (mixed $ratio): float => max(0.45, min(0.78, (float) $ratio)),
            $ratios,
        );
        $ratios = array_values(array_unique($ratios));

        usort($ratios, static fn (float $a, float $b): int => abs($a - $primary) <=> abs($b - $primary));

        return array_slice($ratios, 0, max(1, (int) config('passport.smart_scanner.max_mrz_candidates', 4)));
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
            mrzCandidatePaths: [$imagePath],
        );
    }
}
