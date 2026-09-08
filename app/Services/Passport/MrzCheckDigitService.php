<?php

namespace App\Services\Passport;

use InvalidArgumentException;

final class MrzCheckDigitService
{
    private const WEIGHTS = [7, 3, 1];

    public function calculate(string $value): int
    {
        $total = 0;

        foreach (str_split($value) as $index => $character) {
            $total += $this->characterValue($character) * self::WEIGHTS[$index % 3];
        }

        return $total % 10;
    }

    public function matches(string $value, string $checkDigit): bool
    {
        return ctype_digit($checkDigit) && $this->calculate($value) === (int) $checkDigit;
    }

    private function characterValue(string $character): int
    {
        if (ctype_digit($character)) {
            return (int) $character;
        }

        if ($character === '<') {
            return 0;
        }

        if ($character >= 'A' && $character <= 'Z') {
            return ord($character) - 55;
        }

        throw new InvalidArgumentException("Invalid MRZ character: {$character}");
    }
}
