<?php

namespace App\Services\Passport\VisualZone;

final class VisualZoneFieldExtractor
{
    /** @var array<string, array<int, string>> */
    private const LABELS = [
        'surname' => ['Family name', 'Last name', 'Surname', 'اسم العائلة', 'اللقب'],
        'given_names' => ['Given names', 'Given name', 'First name', 'الأسماء', 'الاسم'],
        'passport_number' => ['Passport Number', 'Passport No.', 'Passport No', 'رقم جواز السفر', 'رقم الجواز'],
        'nationality' => ['Nationality', 'الجنسية'],
        'date_of_birth' => ['Date of birth', 'Birth date', 'تاريخ الميلاد'],
        'sex' => ['Gender', 'Sex', 'الجنس'],
        'expiry_date' => ['Date of expiration', 'Date of expiry', 'Expiry date', 'تاريخ انتهاء الصلاحية', 'تاريخ الانتهاء', 'تاريخ الصلاحية'],
        'place_of_birth' => ['Place of birth', 'Birth place', 'مكان الميلاد'],
        'issue_date' => ['Date of issue', 'Issue date', 'تاريخ الإصدار'],
        'issuing_place' => ['Issuing authority', 'Issuing place', 'Place of issue', 'جهة الإصدار', 'مكان الإصدار'],
    ];

    public function extract(string $text, array $lineMetadata = []): array
    {
        $lines = array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\R/u', $text) ?: []),
            static fn (string $line): bool => $line !== ''
        ));

        $fields = [];

        foreach ($lines as $index => $line) {
            $field = $this->detectField($line);

            if ($field === null) {
                continue;
            }

            $value = $this->valueWithoutLabels($line, self::LABELS[$field]);
            $confidence = $this->lineConfidence($lineMetadata, $index, 0.90);

            if ($value === '' && isset($lines[$index + 1]) && $this->detectField($lines[$index + 1]) === null) {
                $value = $this->cleanValue($lines[$index + 1]);
                $confidence = $this->lineConfidence($lineMetadata, $index + 1, 0.78);
            }

            if ($value === '') {
                continue;
            }

            $candidate = [
                'value' => $value,
                'language' => $this->detectLanguage($value),
                'confidence' => $confidence,
            ];

            if (! isset($fields[$field]) || $candidate['confidence'] > $fields[$field]['confidence']) {
                $fields[$field] = $candidate;
            }
        }

        return $fields;
    }

    private function detectField(string $line): ?string
    {
        foreach (self::LABELS as $field => $labels) {
            foreach ($this->labelsLongestFirst($labels) as $label) {
                if (mb_stripos($line, $label, 0, 'UTF-8') !== false) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function valueWithoutLabels(string $line, array $labels): string
    {
        $value = $line;

        foreach ($this->labelsLongestFirst($labels) as $label) {
            $value = preg_replace('/'.preg_quote($label, '/').'/iu', ' ', $value) ?? $value;
        }

        return $this->cleanValue($value);
    }

    private function cleanValue(string $value): string
    {
        $value = preg_replace('/^[\s:;|\/\\\-–—]+|[\s:;|\/\\\-–—]+$/u', '', trim($value)) ?? trim($value);
        $value = preg_replace('/\s{2,}/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function detectLanguage(string $value): string
    {
        $hasArabic = preg_match('/\p{Arabic}/u', $value) === 1;
        $hasLatin = preg_match('/[A-Za-z]/', $value) === 1;

        return match (true) {
            $hasArabic && $hasLatin => 'mixed',
            $hasArabic => 'ar',
            $hasLatin => 'en',
            default => 'unknown',
        };
    }

    private function labelsLongestFirst(array $labels): array
    {
        usort($labels, static fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        return $labels;
    }

    private function lineConfidence(array $lineMetadata, int $index, float $fallback): float
    {
        $confidence = $lineMetadata[$index]['confidence'] ?? null;

        if (! is_numeric($confidence)) {
            return $fallback;
        }

        return max(0.0, min(1.0, (float) $confidence));
    }
}
