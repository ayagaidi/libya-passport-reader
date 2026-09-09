<?php

namespace App\Services\CivilRegistry;

use App\Exceptions\CivilRegistryDocumentNotDetectedException;

final class CivilRegistryDocumentClassifier
{
    public function detect(string $text): array
    {
        $normalized = $this->normalize($text);

        $scores = [
            'family_status_certificate' => $this->score($normalized, [
                'شهاده بالوضع العائلي' => 8,
                'الوضع العائلي' => 6,
                'حاله القرابه' => 3,
                'اسم الام' => 2,
                'تاريخ الميلاد' => 1,
            ]),
            'residence_certificate' => $this->score($normalized, [
                'شهاده الاقامه' => 8,
                'الرقم الوطني' => 3,
                'من تاريخ' => 1,
                'مهنته' => 1,
                'مهنتها' => 1,
            ]),
        ];

        $authorityScore = $this->score($normalized, [
            'مصلحه الاحوال المدنيه' => 4,
            'الاحوال المدنيه' => 2,
            'civil registry authority' => 4,
        ]);

        arsort($scores);
        $type = (string) array_key_first($scores);
        $typeScore = (int) ($scores[$type] ?? 0);

        if ($typeScore < 5 && $authorityScore < 3) {
            throw new CivilRegistryDocumentNotDetectedException('The document could not be classified as a supported Civil Registry Authority document.');
        }

        if ($typeScore < 5) {
            $type = 'unknown_civil_registry_document';
        }

        return [
            'type' => $type,
            'confidence' => min(1.0, round(($typeScore + min($authorityScore, 4)) / 12, 3)),
            'authority_detected' => $authorityScore >= 2,
        ];
    }

    private function score(string $text, array $keywords): int
    {
        $score = 0;

        foreach ($keywords as $keyword => $weight) {
            if (str_contains($text, $keyword)) {
                $score += $weight;
            }
        }

        return $score;
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
