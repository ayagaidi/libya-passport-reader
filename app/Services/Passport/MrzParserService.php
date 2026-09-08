<?php

namespace App\Services\Passport;

use App\DTO\PassportData;
use InvalidArgumentException;

final class MrzParserService
{
    public function __construct(private readonly MrzCheckDigitService $checkDigits) {}

    public function parse(string $line1, string $line2): array
    {
        $line1 = $this->normalize($line1);
        $line2 = $this->normalize($line2);

        $this->assertTd3Line($line1, 'line1');
        $this->assertTd3Line($line2, 'line2');

        [$surname, $givenNames] = $this->parseNames(substr($line1, 5, 39));

        $passportNumberRaw = substr($line2, 0, 9);
        $birthRaw = substr($line2, 13, 6);
        $expiryRaw = substr($line2, 21, 6);
        $personalNumberRaw = substr($line2, 28, 14);

        $validation = [
            'passport_number' => $this->checkDigits->matches($passportNumberRaw, $line2[9]),
            'date_of_birth' => $this->checkDigits->matches($birthRaw, $line2[19]),
            'expiry_date' => $this->checkDigits->matches($expiryRaw, $line2[27]),
            'personal_number' => $line2[42] === '<' || $this->checkDigits->matches($personalNumberRaw, $line2[42]),
            'composite' => $this->checkDigits->matches(
                substr($line2, 0, 10).substr($line2, 13, 7).substr($line2, 21, 22),
                $line2[43]
            ),
        ];

        $validation['mrz_valid'] = ! in_array(false, $validation, true);

        $issuingCountry = substr($line1, 2, 3);

        $data = new PassportData(
            documentType: rtrim(substr($line1, 0, 2), '<'),
            issuingCountry: $issuingCountry,
            surname: $surname,
            givenNames: $givenNames,
            passportNumber: rtrim($passportNumberRaw, '<'),
            nationality: substr($line2, 10, 3),
            dateOfBirth: $this->parseDate($birthRaw, true),
            sex: $line2[20] === '<' ? 'unspecified' : $line2[20],
            expiryDate: $this->parseDate($expiryRaw, false),
            personalNumber: $this->nullableMrzValue($personalNumberRaw),
            isLibyanPassport: $issuingCountry === 'LBY',
        );

        return [
            'document_format' => 'TD3',
            'data' => $data->toArray(),
            'validation' => $validation,
        ];
    }

    private function normalize(string $line): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($line)) ?? '');
    }

    private function assertTd3Line(string $line, string $field): void
    {
        if (strlen($line) !== 44 || preg_match('/^[A-Z0-9<]{44}$/', $line) !== 1) {
            throw new InvalidArgumentException("{$field} must be exactly 44 ICAO MRZ characters.");
        }
    }

    private function parseNames(string $value): array
    {
        [$surnameRaw, $givenRaw] = array_pad(explode('<<', $value, 2), 2, '');

        $surname = trim(str_replace('<', ' ', $surnameRaw));
        $given = array_values(array_filter(
            preg_split('/<+/', trim($givenRaw, '<')) ?: [],
            static fn (string $name): bool => $name !== ''
        ));

        return [$surname, $given];
    }

    private function parseDate(string $value, bool $birthDate): ?string
    {
        if (preg_match('/^\d{6}$/', $value) !== 1) {
            return null;
        }

        $year = (int) substr($value, 0, 2);
        $month = (int) substr($value, 2, 2);
        $day = (int) substr($value, 4, 2);

        if ($birthDate) {
            $currentTwoDigitYear = (int) date('y');
            $fullYear = $year <= $currentTwoDigitYear ? 2000 + $year : 1900 + $year;
        } else {
            $fullYear = 2000 + $year;
        }

        if (! checkdate($month, $day, $fullYear)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $fullYear, $month, $day);
    }

    private function nullableMrzValue(string $value): ?string
    {
        $clean = trim(str_replace('<', '', $value));

        return $clean === '' ? null : $clean;
    }
}
