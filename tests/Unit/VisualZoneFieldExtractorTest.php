<?php

namespace Tests\Unit;

use App\Services\Passport\VisualZone\VisualZoneFieldExtractor;
use PHPUnit\Framework\TestCase;

final class VisualZoneFieldExtractorTest extends TestCase
{
    public function test_it_extracts_bilingual_labeled_fields_without_returning_raw_ocr(): void
    {
        $extractor = new VisualZoneFieldExtractor();

        $fields = $extractor->extract(implode("\n", [
            'Surname / اللقب: TESTER',
            'Given names / الاسم: SAMPLE',
            'Passport No / رقم الجواز: DEMO12345',
            'Nationality / الجنسية: LIBYAN',
            'Date of birth / تاريخ الميلاد: 01/01/1995',
            'Sex / الجنس: F',
            'Date of expiry / تاريخ الانتهاء: 01/01/2030',
            'Place of birth / مكان الميلاد: DEMO CITY',
            'Date of issue / تاريخ الإصدار: 02/02/2025',
            'Place of issue / مكان الإصدار: DEMO OFFICE',
        ]));

        self::assertSame('TESTER', $fields['surname']['value']);
        self::assertSame('SAMPLE', $fields['given_names']['value']);
        self::assertSame('DEMO12345', $fields['passport_number']['value']);
        self::assertSame('DEMO CITY', $fields['place_of_birth']['value']);
        self::assertSame('DEMO OFFICE', $fields['issuing_place']['value']);
        self::assertArrayNotHasKey('raw_ocr_text', $fields);
    }
}
