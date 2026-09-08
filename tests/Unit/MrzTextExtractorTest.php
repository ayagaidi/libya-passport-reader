<?php

namespace Tests\Unit;

use App\Services\Passport\MrzCheckDigitService;
use App\Services\Passport\Ocr\MrzTextExtractor;
use PHPUnit\Framework\TestCase;

final class MrzTextExtractorTest extends TestCase
{
    private const LINE_1 = 'P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<';

    private const LINE_2 = '1234567897LBY9501016F3001019<<<<<<<<<<<<<<02';

    public function test_it_finds_td3_lines_inside_noisy_ocr_text(): void
    {
        $extractor = new MrzTextExtractor(new MrzCheckDigitService);
        $text = implode("\n", [
            'STATE OF LIBYA PASSPORT',
            'SURNAME GIVEN NAMES NATIONALITY',
            self::LINE_1,
            self::LINE_2,
            'SIGNATURE OF BEARER',
        ]);

        self::assertSame(
            [self::LINE_1, self::LINE_2],
            $extractor->extract($text),
        );
    }
}
