<?php

namespace Tests\Unit;

use App\Services\CivilRegistry\CivilRegistryFieldExtractor;
use PHPUnit\Framework\TestCase;

final class CivilRegistryFieldExtractorTest extends TestCase
{
    public function test_extracts_residence_fields(): void
    {
        $text = implode("\n", [
            'شهادة الإقامة',
            'الرقم الوطني 123456789012',
            'بيان السيدة سارة علي سالم',
            'اسم والدها علي سالم',
            'اسم والدتها فاطمة محمد',
            'تاريخ مولدها 1995-03-28',
            'مهنتها مهندسة',
            'مقيمه بالعنوان طرابلس',
            'مسجلة بالسجل المدني بتاريخ 1995-01-01',
        ]);

        $result = (new CivilRegistryFieldExtractor)->extract('residence_certificate', $text);

        $this->assertSame('123456789012', $result['fields']['national_number']);
        $this->assertSame('1995-03-28', $result['fields']['date_of_birth']);
        $this->assertSame('1995-01-01', $result['fields']['registered_since']);
        $this->assertSame([], $result['family_members']);
    }

    public function test_extracts_family_member_identifiers_dates_and_relationships(): void
    {
        $text = implode("\n", [
            'شهادة بالوضع العائلي',
            '123456789012 سارة علي ابنة 2000-01-02',
            '123456789013 محمد علي ابن 2002-03-04',
        ]);

        $result = (new CivilRegistryFieldExtractor)->extract('family_status_certificate', $text);

        $this->assertCount(2, $result['family_members']);
        $this->assertSame('123456789012', $result['family_members'][0]['national_number']);
        $this->assertSame('2000-01-02', $result['family_members'][0]['date_of_birth']);
        $this->assertSame('daughter', $result['family_members'][0]['relationship']);
        $this->assertSame('son', $result['family_members'][1]['relationship']);
    }
}
