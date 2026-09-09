<?php

namespace Tests\Unit;

use App\Exceptions\CivilRegistryDocumentNotDetectedException;
use App\Services\CivilRegistry\CivilRegistryDocumentClassifier;
use PHPUnit\Framework\TestCase;

final class CivilRegistryDocumentClassifierTest extends TestCase
{
    public function test_detects_residence_certificate(): void
    {
        $result = (new CivilRegistryDocumentClassifier)->detect(
            "مصلحة الأحوال المدنية\nشهادة الإقامة\nالرقم الوطني 123456789012"
        );

        $this->assertSame('residence_certificate', $result['type']);
        $this->assertTrue($result['authority_detected']);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function test_detects_family_status_certificate(): void
    {
        $result = (new CivilRegistryDocumentClassifier)->detect(
            "CIVIL REGISTRY AUTHORITY\nشهادة بالوضع العائلي\nحالة القرابة\nتاريخ الميلاد"
        );

        $this->assertSame('family_status_certificate', $result['type']);
        $this->assertTrue($result['authority_detected']);
    }

    public function test_rejects_unrelated_text(): void
    {
        $this->expectException(CivilRegistryDocumentNotDetectedException::class);

        (new CivilRegistryDocumentClassifier)->detect('ordinary unrelated document');
    }
}
