<?php

namespace App\Services\Passport\VisualZone;

final class TesseractTsvParser
{
    public function parse(string $tsv): array
    {
        $rows = preg_split('/\R/u', trim($tsv)) ?: [];
        $lines = [];
        $allConfidences = [];

        foreach ($rows as $index => $row) {
            if ($index === 0 || trim($row) === '') {
                continue;
            }

            $columns = explode("\t", $row, 12);

            if (count($columns) < 12) {
                continue;
            }

            [$level, $page, $block, $paragraph, $line, $word, $left, $top, $width, $height, $confidence, $text] = $columns;

            if ((int) $level !== 5 || trim($text) === '') {
                continue;
            }

            $confidenceValue = (float) $confidence;

            if ($confidenceValue >= 0) {
                $allConfidences[] = $confidenceValue;
            }

            $key = implode(':', [$page, $block, $paragraph, $line]);

            if (! isset($lines[$key])) {
                $lines[$key] = [
                    'words' => [],
                    'confidences' => [],
                    'left' => (int) $left,
                    'top' => (int) $top,
                    'right' => (int) $left + (int) $width,
                    'bottom' => (int) $top + (int) $height,
                ];
            }

            $lines[$key]['words'][] = trim($text);

            if ($confidenceValue >= 0) {
                $lines[$key]['confidences'][] = $confidenceValue;
            }

            $lines[$key]['left'] = min($lines[$key]['left'], (int) $left);
            $lines[$key]['top'] = min($lines[$key]['top'], (int) $top);
            $lines[$key]['right'] = max($lines[$key]['right'], (int) $left + (int) $width);
            $lines[$key]['bottom'] = max($lines[$key]['bottom'], (int) $top + (int) $height);
        }

        $parsedLines = [];

        foreach ($lines as $line) {
            $parsedLines[] = [
                'text' => implode(' ', $line['words']),
                'confidence' => $this->average($line['confidences']),
                'box' => [
                    'left' => $line['left'],
                    'top' => $line['top'],
                    'width' => $line['right'] - $line['left'],
                    'height' => $line['bottom'] - $line['top'],
                ],
            ];
        }

        return [
            'text' => implode("\n", array_column($parsedLines, 'text')),
            'confidence' => $this->average($allConfidences),
            'lines' => $parsedLines,
        ];
    }

    private function average(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values) / 100, 4);
    }
}
