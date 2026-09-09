<?php

namespace App\Services\Passport\Ocr;

use App\Exceptions\MrzNotDetectedException;
use App\Services\Passport\MrzCheckDigitService;

final class MrzTextExtractor
{
    public function __construct(private readonly MrzCheckDigitService $checkDigits) {}

    public function extract(string $text): array
    {
        $result = $this->detect($text);

        return [$result['line1'], $result['line2']];
    }

    public function detect(string $text): array
    {
        $lines = preg_split('/\R/u', strtoupper($text)) ?: [];
        $candidates = [];

        foreach ($lines as $line) {
            $normalized = preg_replace('/[^A-Z0-9<]/', '', $line) ?? '';

            if (strlen($normalized) >= 20) {
                $candidates[] = $normalized;
            }
        }

        [$line1, $line1Score] = $this->bestLine1($candidates);
        [$line2, $line2Score] = $this->bestLine2($candidates, $line1);

        if ($line1 === null || $line2 === null) {
            throw new MrzNotDetectedException('No TD3 MRZ could be confidently detected in the OCR output.');
        }

        return [
            'line1' => $line1,
            'line2' => $line2,
            'score' => $line1Score + $line2Score,
            'line1_score' => $line1Score,
            'line2_score' => $line2Score,
        ];
    }

    private function bestLine1(array $candidates): array
    {
        $best = null;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            foreach ($this->windows($candidate) as $window) {
                $score = 0;

                if (str_starts_with($window, 'P<')) {
                    $score += 10;
                }

                if (ctype_alpha(substr($window, 2, 3))) {
                    $score += 3;
                }

                if (str_contains(substr($window, 5), '<<')) {
                    $score += 2;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $window;
                }
            }
        }

        return $bestScore >= 10 ? [$best, $bestScore] : [null, $bestScore];
    }

    private function bestLine2(array $candidates, ?string $line1): array
    {
        $best = null;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            foreach ($this->windows($candidate) as $window) {
                if ($window === $line1) {
                    continue;
                }

                $score = $this->scoreLine2($window);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $window;
                }
            }
        }

        return $bestScore >= 8 ? [$best, $bestScore] : [null, $bestScore];
    }

    private function scoreLine2(string $line): int
    {
        $score = 0;

        if (ctype_alpha(substr($line, 10, 3))) {
            $score += 2;
        }

        if (ctype_digit(substr($line, 13, 6))) {
            $score += 2;
        }

        if (ctype_digit(substr($line, 21, 6))) {
            $score += 2;
        }

        if (in_array($line[20], ['M', 'F', 'X', '<'], true)) {
            $score++;
        }

        if ($this->checkDigits->matches(substr($line, 0, 9), $line[9])) {
            $score += 3;
        }

        if ($this->checkDigits->matches(substr($line, 13, 6), $line[19])) {
            $score += 3;
        }

        if ($this->checkDigits->matches(substr($line, 21, 6), $line[27])) {
            $score += 3;
        }

        if ($this->checkDigits->matches(substr($line, 0, 10).substr($line, 13, 7).substr($line, 21, 22), $line[43])) {
            $score += 4;
        }

        return $score;
    }

    private function windows(string $candidate): array
    {
        $length = strlen($candidate);

        if ($length < 44) {
            return [];
        }

        $windows = [];

        for ($offset = 0; $offset <= $length - 44; $offset++) {
            $windows[] = substr($candidate, $offset, 44);
        }

        return $windows;
    }
}
