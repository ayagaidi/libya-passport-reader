<?php

namespace App\Services\CivilRegistry;

final class CivilRegistryFieldExtractor
{
    public function extract(string $documentType, string $text, array $lines = []): array
    {
        $lineTexts = $this->lineTexts($text, $lines);
        $fields = [
            'national_number' => $this->firstNationalNumber($text),
            'family_registry_number' => $this->valueAfterLabels($lineTexts, ['رقم قيد العائلة', 'رقم قيد العائله', 'رقم القيد']),
            'family_sheet_number' => $this->valueAfterLabels($lineTexts, ['رقم ورقة العائلة', 'رقم ورقه العائله']),
        ];

        if ($documentType === 'residence_certificate') {
            $fields = array_merge($fields, [
                'person_name' => $this->valueAfterLabels($lineTexts, ['بيان السيد', 'بيان السيدة', 'بيان السيده']),
                'father_name' => $this->valueAfterLabels($lineTexts, ['اسم والده', 'اسم والدها']),
                'mother_name' => $this->valueAfterLabels($lineTexts, ['اسم والدته', 'اسم والدتها']),
                'date_of_birth' => $this->dateAfterLabels($lineTexts, ['تاريخ مولده', 'تاريخ مولدها']),
                'profession' => $this->valueAfterLabels($lineTexts, ['مهنته', 'مهنتها', 'المهنة', 'المهنه']),
                'address' => $this->valueAfterLabels($lineTexts, ['مقيم بالعنوان', 'مقيمه بالعنوان', 'ومقيم بالعنوان', 'ومقيمه بالعنوان']),
                'registered_since' => $this->dateAfterLabels($lineTexts, ['مسجل بالسجل المدني بتاريخ', 'مسجلة بالسجل المدني بتاريخ', 'مسجله بالسجل المدني بتاريخ']),
            ]);
        }

        $fields = array_filter($fields, static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'fields' => $fields,
            'family_members' => $documentType === 'family_status_certificate'
                ? $this->familyMembers($lineTexts)
                : [],
        ];
    }

    private function familyMembers(array $lines): array
    {
        $members = [];
        $seen = [];

        foreach ($lines as $line) {
            if (! preg_match('/(?<!\d)(\d{12})(?!\d)/u', $line, $nationalNumberMatch)) {
                continue;
            }

            $nationalNumber = $nationalNumberMatch[1];

            if (isset($seen[$nationalNumber])) {
                continue;
            }

            $member = [
                'national_number' => $nationalNumber,
                'date_of_birth' => $this->firstDate($line),
                'relationship' => $this->relationshipFromLine($line),
                'name' => $this->nameFromFamilyRow($line, $nationalNumber),
            ];

            $members[] = array_filter($member, static fn (mixed $value): bool => $value !== null && $value !== '');
            $seen[$nationalNumber] = true;
        }

        return $members;
    }

    private function nameFromFamilyRow(string $line, string $nationalNumber): ?string
    {
        $clean = str_replace($nationalNumber, ' ', $line);
        $clean = preg_replace('/\b\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}\b/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\b\d{1,2}[-\/.]\d{1,2}[-\/.]\d{4}\b/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\b\d+\b/u', ' ', $clean) ?? $clean;
        $clean = str_replace(['ابن', 'ابنة', 'ابنه', 'زوج', 'زوجة', 'زوجه', 'الاب', 'الأب', 'الام', 'الأم'], ' ', $clean);
        $clean = preg_replace('/[^\p{Arabic}\s]/u', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\s+/u', ' ', trim($clean)) ?? trim($clean);

        $tokens = preg_split('/\s+/u', $clean) ?: [];

        if (count($tokens) < 2 || count($tokens) > 8) {
            return null;
        }

        return implode(' ', $tokens);
    }

    private function relationshipFromLine(string $line): ?string
    {
        $relationships = ['ابنة', 'ابنه', 'ابن', 'زوجة', 'زوجه', 'زوج', 'الأب', 'الاب', 'الأم', 'الام'];

        foreach ($relationships as $relationship) {
            if (str_contains($line, $relationship)) {
                return match ($relationship) {
                    'ابنة', 'ابنه' => 'daughter',
                    'ابن' => 'son',
                    'زوجة', 'زوجه' => 'wife',
                    'زوج' => 'husband',
                    'الأب', 'الاب' => 'father',
                    'الأم', 'الام' => 'mother',
                    default => null,
                };
            }
        }

        return null;
    }

    private function valueAfterLabels(array $lines, array $labels): ?string
    {
        foreach ($lines as $index => $line) {
            foreach ($labels as $label) {
                $position = mb_stripos($line, $label, 0, 'UTF-8');

                if ($position === false) {
                    continue;
                }

                $value = trim(mb_substr($line, $position + mb_strlen($label, 'UTF-8'), null, 'UTF-8'));
                $value = preg_replace('/^[\s:：\-\.\/]+/u', '', $value) ?? $value;

                if ($value !== '' && mb_strlen($value, 'UTF-8') >= 2) {
                    return $value;
                }

                $next = trim((string) ($lines[$index + 1] ?? ''));

                if ($next !== '') {
                    return $next;
                }
            }
        }

        return null;
    }

    private function dateAfterLabels(array $lines, array $labels): ?string
    {
        foreach ($lines as $index => $line) {
            foreach ($labels as $label) {
                if (mb_stripos($line, $label, 0, 'UTF-8') === false) {
                    continue;
                }

                $date = $this->firstDate($line);

                if ($date !== null) {
                    return $date;
                }

                $date = $this->firstDate((string) ($lines[$index + 1] ?? ''));

                if ($date !== null) {
                    return $date;
                }
            }
        }

        return null;
    }

    private function firstNationalNumber(string $text): ?string
    {
        return preg_match('/(?<!\d)(\d{12})(?!\d)/u', $text, $match) ? $match[1] : null;
    }

    private function firstDate(string $text): ?string
    {
        if (preg_match('/\b(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/u', $text, $match)) {
            return sprintf('%04d-%02d-%02d', (int) $match[1], (int) $match[2], (int) $match[3]);
        }

        if (preg_match('/\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\b/u', $text, $match)) {
            return sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]);
        }

        return null;
    }

    private function lineTexts(string $text, array $metadata): array
    {
        $lines = [];

        foreach ($metadata as $line) {
            $value = trim((string) ($line['text'] ?? ''));

            if ($value !== '') {
                $lines[] = $value;
            }
        }

        if ($lines !== []) {
            return $lines;
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));
    }
}
