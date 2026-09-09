<?php

namespace Tests\Unit;

use App\Services\Passport\SmartScanner\PassportRegionGeometry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PassportRegionGeometryTest extends TestCase
{
    public function test_it_calculates_mrz_and_visual_regions_from_image_dimensions(): void
    {
        $geometry = new PassportRegionGeometry;

        $regions = $geometry->calculate(2000, 1000, 0.62, 0.78);

        self::assertSame([
            'x' => 0,
            'y' => 620,
            'width' => 2000,
            'height' => 380,
        ], $regions['mrz']);

        self::assertSame([
            'x' => 0,
            'y' => 0,
            'width' => 2000,
            'height' => 780,
        ], $regions['visual_zone']);
    }

    public function test_it_rejects_invalid_ratios(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PassportRegionGeometry)->calculate(2000, 1000, 1.1, 0.78);
    }
}
