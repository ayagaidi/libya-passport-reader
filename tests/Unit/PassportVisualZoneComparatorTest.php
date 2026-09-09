<?php

namespace Tests\Unit;

use App\Services\Passport\VisualZone\PassportVisualZoneComparator;
use PHPUnit\Framework\TestCase;

final class PassportVisualZoneComparatorTest extends TestCase
{
    public function test_it_reports_consistent_comparable_fields_and_ignores_visual_only_fields(): void
    {
        $comparator = new PassportVisualZoneComparator;

        $visual = [
            'surname' => ['value' => 'TESTER', 'language' => 'en', 'confidence' => 0.90],
            'given_names' => ['value' => 'SAMPLE', 'language' => 'en', 'confidence' => 0.90],
            'passport_number' => ['value' => 'DEMO12345', 'language' => 'en', 'confidence' => 0.90],
            'nationality' => ['value' => 'LIBYAN', 'language' => 'en', 'confidence' => 0.90],
            'date_of_birth' => ['value' => '01/01/1995', 'language' => 'unknown', 'confidence' => 0.90],
            'sex' => ['value' => 'F', 'language' => 'en', 'confidence' => 0.90],
            'expiry_date' => ['value' => '01/01/2030', 'language' => 'unknown', 'confidence' => 0.90],
            'place_of_birth' => ['value' => 'DEMO CITY', 'language' => 'en', 'confidence' => 0.90],
        ];

        $mrz = [
            'surname' => 'TESTER',
            'given_names' => ['SAMPLE'],
            'passport_number' => 'DEMO12345',
            'nationality' => 'LBY',
            'date_of_birth' => '1995-01-01',
            'sex' => 'F',
            'expiry_date' => '2030-01-01',
        ];

        $result = $comparator->compare($visual, $mrz);

        self::assertSame('consistent', $result['status']);
        self::assertSame(7, $result['compared_fields']);
        self::assertSame(7, $result['matches']);
        self::assertSame(0, $result['mismatches']);
        self::assertArrayNotHasKey('place_of_birth', $result['fields']);
    }

    public function test_it_does_not_equate_arabic_names_with_mrz_transliteration(): void
    {
        $comparator = new PassportVisualZoneComparator;

        $result = $comparator->compare([
            'surname' => ['value' => 'اختبار', 'language' => 'ar', 'confidence' => 0.90],
        ], [
            'surname' => 'TESTER',
        ]);

        self::assertSame('not_comparable', $result['fields']['surname']['status']);
        self::assertNull($result['fields']['surname']['match']);
    }
}
