<?php

namespace Tests\Unit;

use App\Services\CivilRegistry\CivilRegistryTemplateVerifier;
use PHPUnit\Framework\TestCase;

final class CivilRegistryTemplateVerifierTest extends TestCase
{
    public function test_it_scores_residence_template_anchors_and_visual_positions(): void
    {
        $result = (new CivilRegistryTemplateVerifier)->verify(
            'residence_certificate',
            implode("\n", [
                'مصلحة الأحوال المدنية',
                'شهادة الإقامة',
                'الرقم الوطني 123456789012',
                'مكتب السجل المدني سوق الجمعة',
                'رقم قيد العائلة 106101',
            ]),
            [
                'status' => 'processed',
                'qr' => [
                    'detected' => true,
                    'position_consistent' => true,
                ],
                'seals' => [
                    'detected' => true,
                    'expected_location_match' => true,
                ],
            ],
        );

        $this->assertSame('consistent', $result['status']);
        $this->assertSame(1.0, $result['score']);
        $this->assertFalse($result['official_template_verified']);
        $this->assertContains('document_title', $result['anchors']['found']);
    }

    public function test_it_never_claims_an_official_template_match_from_partial_text(): void
    {
        $result = (new CivilRegistryTemplateVerifier)->verify(
            'family_status_certificate',
            'صلة القرابة تاريخ الميلاد',
            [
                'status' => 'processed',
                'qr' => ['detected' => false],
                'seals' => ['detected' => false],
            ],
        );

        $this->assertNotSame('consistent', $result['status']);
        $this->assertFalse($result['official_template_verified']);
    }
}
