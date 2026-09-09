<?php

namespace App\Services\Passport\SmartScanner;

use InvalidArgumentException;

final class PassportRegionGeometry
{
    public function calculate(
        int $width,
        int $height,
        float $mrzStartRatio = 0.62,
        float $visualEndRatio = 0.78,
    ): array {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Image dimensions must be positive.');
        }

        if ($mrzStartRatio <= 0 || $mrzStartRatio >= 1 || $visualEndRatio <= 0 || $visualEndRatio >= 1) {
            throw new InvalidArgumentException('Region ratios must be between 0 and 1.');
        }

        $mrzY = max(0, min($height - 1, (int) floor($height * $mrzStartRatio)));
        $visualHeight = max(1, min($height, (int) ceil($height * $visualEndRatio)));

        return [
            'mrz' => [
                'x' => 0,
                'y' => $mrzY,
                'width' => $width,
                'height' => $height - $mrzY,
            ],
            'visual_zone' => [
                'x' => 0,
                'y' => 0,
                'width' => $width,
                'height' => $visualHeight,
            ],
        ];
    }
}
