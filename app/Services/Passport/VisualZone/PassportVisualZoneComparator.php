<?php

namespace App\Services\Passport\VisualZone;

final class PassportVisualZoneComparator
{
    /** @var array<int, string> */
    private const COMPARABLE_FIELDS = [
        'surname',
        'given_names',
        'passport_number',
        'nationality',
        'date_of_birth',
        'sex',
        'expiry_date',
    ];

    /** @var array<int, string> */
    public const VISUAL_ONLY_FIELDS = [
        'place_of_birth',
        'issue_date',
        'issuing_place',
    ];

    public function compare(array $visualFields, array $mrzData): array
    {
        $fields = [];
        $matches = 0;
        $mismatches = 0;
        $compared = 0;

        foreach (self::COMPARABLE_FIELDS as $field) {
            $visual = $visualFields[$field] ?? null;
            $mrzValue = $mrzData[$field] ?? null;

            if ($visual === null) {
                $fields[$field] = [
                    'status' => 'not_detected',
                    'match' => null,
                ];

                continue;
            }

            if ($mrzValue === null || $mrzValue === '' || $mrzValue === []) {
                $fields[$field] = [
                    'status' => 'mrz_unavailable',
                    'match' => null,
                    'visual_value' => $visual['value'],
                    'confidence' => $visual['confidence'] ?? null,
                ];

                continue;
            }

            if (in_array($field, ['surname', 'given_names'], true) && in_array($visual['language'] ?? null, ['ar', 'mixed'], true)) {
                $fields[$field] = [
                    'status' => 'not_comparable',
                    'match' => null,
                    'reason' => 'Arabic name OCR cannot be safely equated to the Latin MRZ transliteration.',
                    'visual_value' => $visual['value'],
                    'mrz_value' => $mrzValue,
                    'confidence' => $visual['confidence'] ?? null,
                ];

                continue;
            }

            $isMatch = $this->canonical($field, $visual['value']) === $this->canonical($field, $mrzValue);
            $compared++;
            $isMatch ? $matches++ : $mismatches++;

            $fields[$field] = [
                'status' => $isMatch ? 'match' : 'mismatch',
                'match' => $isMatch,
                'visual_value' => $visual['value'],
                'mrz_value' => $mrzValue,
                'confidence' => $visual['confidence'] ?? null,
            ];
        }

        return [
            'status' => match (true) {
                $mismatches > 0 => 'mismatch_detected',
                $compared > 0 => 'consistent',
                default => 'insufficient_data',
            },
            'compared_fields' => $compared,
            'matches' => $matches,
            'mismatches' => $mismatches,
            'fields' => $fields,
        ];
    }

    private function canonical(string $field, mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        $value = trim((string) $value);

        return match ($field) {
            'date_of_birth', 'expiry_date' => $this->canonicalDate($value),
            'sex' => $this->canonicalSex($value),
            'nationality' => $this->canonicalNationality($value),
            'passport_number' => preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? strtoupper($value),
            default => preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? strtoupper($value),
        };
    }

    private function canonicalDate(string $value): string
    {
        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';

        if (strlen($digits) === 8) {
            if ((int) substr($digits, 0, 4) >= 1900) {
                return substr($digits, 0, 4).substr($digits, 4, 2).substr($digits, 6, 2);
            }

            return substr($digits, 4, 4).substr($digits, 2, 2).substr($digits, 0, 2);
        }

        return $digits;
    }

    private function canonicalSex(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return match (true) {
            in_array($normalized, ['m', 'male', 'ذكر'], true) => 'M',
            in_array($normalized, ['f', 'female', 'أنثى', 'انثى'], true) => 'F',
            default => strtoupper($value),
        };
    }

    private function canonicalNationality(string $value): string
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        if (in_array($normalized, ['lby', 'libya', 'libyan', 'ليبي', 'ليبية', 'الجماهيرية العربية الليبية'], true)) {
            return 'LBY';
        }

        return preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? strtoupper($value);
    }
}
