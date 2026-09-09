<?php

namespace Tests\Unit;

use App\DTO\OcrResult;
use App\Services\Passport\MrzCheckDigitService;
use App\Services\Passport\Ocr\MrzTextExtractor;
use App\Services\Passport\Ocr\OcrEngineInterface;
use App\Services\Passport\SmartScanner\AdaptiveMrzScanner;
use PHPUnit\Framework\TestCase;

final class AdaptiveMrzScannerTest extends TestCase
{
    private const LINE_1 = 'P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<';

    private const STRONG_LINE_2 = '1234567897LBY9501016F3001019<<<<<<<<<<<<<<02';

    private const WEAK_LINE_2 = '1234567897LBY9501010F3001010<<<<<<<<<<<<<<00';

    public function test_it_selects_the_candidate_with_the_strongest_icao_detection_score(): void
    {
        $ocr = new class implements OcrEngineInterface
        {
            public function read(string $imagePath): OcrResult
            {
                $line2 = $imagePath === 'strong.png'
                    ? AdaptiveMrzScannerTest::STRONG_LINE_2
                    : AdaptiveMrzScannerTest::WEAK_LINE_2;

                return new OcrResult(
                    engine: 'fake-adaptive-ocr',
                    text: AdaptiveMrzScannerTest::LINE_1."\n".$line2,
                    confidence: $imagePath === 'strong.png' ? 95.0 : 99.0,
                );
            }
        };
        $scanner = new AdaptiveMrzScanner(
            $ocr,
            new MrzTextExtractor(new MrzCheckDigitService),
        );

        $result = $scanner->scan(['weak.png', 'strong.png']);

        self::assertSame(self::STRONG_LINE_2, $result['line2']);
        self::assertSame(1, $result['candidate_index']);
        self::assertSame(2, $result['attempts']);
        self::assertSame(2, $result['candidate_count']);
        self::assertGreaterThan(20, $result['detection_score']);
    }
}
