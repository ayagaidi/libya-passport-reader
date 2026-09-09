<?php

namespace App\Services\CivilRegistry;

final class CivilRegistryTemplateVerifier
{
    public function verify(string $documentType, string $text, array $visualSignals): array
    {
        $profile = $this->profiles()[$documentType] ?? null;

        if ($profile === null) {
            return [
                'status' => 'unsupported_profile',
                'score' => null,
                'profile_source' => 'sample_calibrated',
                'official_template_verified' => false,
                'anchors' => [],
            ];
        }

        $normalized = $this->normalize($text);
        $found = [];
        $missing = [];
        $matchedWeight = 0;
        $totalWeight = 0;

        foreach ($profile['anchors'] as $anchor) {
            $totalWeight += $anchor['weight'];
            $matched = false;

            foreach ($anchor['patterns'] as $pattern) {
                if (str_contains($normalized, $this->normalize($pattern))) {
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                $matchedWeight += $anchor['weight'];
                $found[] = $anchor['name'];
            } else {
                $missing[] = $anchor['name'];
            }
        }

        $textScore = $totalWeight > 0 ? $matchedWeight / $totalWeight : 0.0;
        $components = [
            ['weight' => 0.70, 'score' => $textScore],
        ];

        if (($visualSignals['status'] ?? null) === 'processed') {
            $qr = is_array($visualSignals['qr'] ?? null) ? $visualSignals['qr'] : [];
            $seals = is_array($visualSignals['seals'] ?? null) ? $visualSignals['seals'] : [];

            $qrScore = ! ($qr['detected'] ?? false)
                ? 0.0
                : (($qr['position_consistent'] ?? null) === true ? 1.0 : 0.5);
            $sealScore = ! ($seals['detected'] ?? false)
                ? 0.0
                : (($seals['expected_location_match'] ?? null) === true ? 1.0 : 0.5);

            $components[] = ['weight' => 0.15, 'score' => $qrScore];
            $components[] = ['weight' => 0.15, 'score' => $sealScore];
        }

        $weightTotal = array_sum(array_column($components, 'weight'));
        $weightedScore = array_sum(array_map(
            static fn (array $component): float => $component['weight'] * $component['score'],
            $components,
        ));
        $score = $weightTotal > 0 ? $weightedScore / $weightTotal : 0.0;

        return [
            'status' => match (true) {
                $score >= 0.70 => 'consistent',
                $score >= 0.45 => 'partial',
                default => 'insufficient_evidence',
            },
            'score' => round($score, 3),
            'text_anchor_score' => round($textScore, 3),
            'profile_source' => 'sample_calibrated',
            'official_template_verified' => false,
            'anchors' => [
                'found' => $found,
                'missing' => $missing,
            ],
        ];
    }

    private function profiles(): array
    {
        return [
            'residence_certificate' => [
                'anchors' => [
                    [
                        'name' => 'document_title',
                        'patterns' => ['شهادة الإقامة', 'شهاده الاقامه'],
                        'weight' => 4,
                    ],
                    [
                        'name' => 'national_number_label',
                        'patterns' => ['الرقم الوطني'],
                        'weight' => 3,
                    ],
                    [
                        'name' => 'civil_registry_office',
                        'patterns' => ['مكتب السجل المدني', 'السجل المدني'],
                        'weight' => 2,
                    ],
                    [
                        'name' => 'family_registry_label',
                        'patterns' => ['رقم قيد العائلة', 'رقم قيد العائله'],
                        'weight' => 1,
                    ],
                    [
                        'name' => 'civil_registry_authority',
                        'patterns' => ['مصلحة الأحوال المدنية', 'مصلحه الاحوال المدنيه', 'civil registry authority'],
                        'weight' => 2,
                    ],
                ],
            ],
            'family_status_certificate' => [
                'anchors' => [
                    [
                        'name' => 'document_title',
                        'patterns' => ['شهادة بالوضع العائلي', 'شهاده بالوضع العائلي', 'الوضع العائلي'],
                        'weight' => 4,
                    ],
                    [
                        'name' => 'national_number_column',
                        'patterns' => ['الرقم الوطني'],
                        'weight' => 2,
                    ],
                    [
                        'name' => 'relationship_column',
                        'patterns' => ['صلة القرابة', 'صله القرابه'],
                        'weight' => 2,
                    ],
                    [
                        'name' => 'birth_date_column',
                        'patterns' => ['تاريخ الميلاد'],
                        'weight' => 2,
                    ],
                    [
                        'name' => 'civil_registry_authority',
                        'patterns' => ['مصلحة الأحوال المدنية', 'مصلحه الاحوال المدنيه', 'civil registry authority'],
                        'weight' => 2,
                    ],
                ],
            ],
        ];
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(['ـ', 'أ', 'إ', 'آ', 'ٱ', 'ة', 'ى'], ['', 'ا', 'ا', 'ا', 'ا', 'ه', 'ي'], $text);
        $text = preg_replace('/[ًٌٍَُِّْـ]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
