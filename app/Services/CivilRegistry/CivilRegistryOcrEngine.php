<?php

namespace App\Services\CivilRegistry;

use App\DTO\OcrResult;
use App\Exceptions\ScannerDependencyException;
use App\Services\Passport\VisualZone\TesseractTsvParser;
use Symfony\Component\Process\Process;
use Throwable;

final class CivilRegistryOcrEngine implements CivilRegistryOcrEngineInterface
{
    public function __construct(private readonly TesseractTsvParser $tsvParser) {}

    public function read(string $imagePath): OcrResult
    {
        $language = (string) config('passport.civil_registry.language', 'ara+eng');
        $psms = config('passport.civil_registry.psm_candidates', [4, 3, 11]);
        $psms = is_array($psms) ? $psms : [4, 3, 11];
        $candidates = [];

        foreach ($psms as $psm) {
            $process = $this->run($imagePath, $language, (int) $psm);

            if (! $process->isSuccessful()) {
                continue;
            }

            $parsed = $this->tsvParser->parse($process->getOutput());

            if (trim($parsed['text']) === '') {
                continue;
            }

            $candidates[] = [
                'psm' => (int) $psm,
                'parsed' => $parsed,
                'score' => $this->score($parsed['text'], $parsed['confidence']),
            ];
        }

        if ($candidates === []) {
            throw new ScannerDependencyException('The civil-registry OCR engine failed to process the document.');
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $best = $candidates[0];
        $mergedLines = $this->mergeLines($candidates);

        return new OcrResult(
            engine: 'tesseract-civil-registry:'.$language.':psm'.$best['psm'],
            text: implode("\n", array_column($mergedLines, 'text')),
            confidence: $best['parsed']['confidence'],
            metadata: [
                'lines' => $mergedLines,
                'format' => 'tsv',
                'layout_candidates' => array_map(
                    static fn (array $candidate): array => [
                        'psm' => $candidate['psm'],
                        'confidence' => $candidate['parsed']['confidence'],
                        'score' => round($candidate['score'], 3),
                    ],
                    $candidates,
                ),
            ],
        );
    }

    private function run(string $imagePath, string $language, int $psm): Process
    {
        $process = new Process([
            (string) config('passport.ocr.tesseract_binary', 'tesseract'),
            $imagePath,
            'stdout',
            '-l',
            $language,
            '--psm',
            (string) $psm,
            'tsv',
        ]);
        $process->setTimeout((float) config('passport.civil_registry.timeout', 30));

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new ScannerDependencyException('The civil-registry OCR engine could not be started.', previous: $exception);
        }

        return $process;
    }

    private function score(string $text, ?float $confidence): float
    {
        $score = ($confidence ?? 0.0) * 4;
        $normalized = str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], mb_strtolower($text, 'UTF-8'));

        foreach ([
            'civil registry authority' => 4,
            'مصلحه الاحوال المدنيه' => 4,
            'شهاده بالوضع العائلي' => 6,
            'شهاده الاقامه' => 6,
            'الرقم الوطني' => 2,
            'تاريخ الميلاد' => 1,
            'صله القرابه' => 2,
        ] as $keyword => $weight) {
            if (str_contains($normalized, $keyword)) {
                $score += $weight;
            }
        }

        preg_match_all('/(?<!\d)\d{12}(?!\d)/u', $text, $nationalNumbers);
        preg_match_all('/\b\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}\b/u', $text, $dates);
        $score += min(12, count($nationalNumbers[0] ?? []) * 2);
        $score += min(6, count($dates[0] ?? []));

        return $score;
    }

    private function mergeLines(array $candidates): array
    {
        $merged = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            foreach ($candidate['parsed']['lines'] as $line) {
                $text = trim((string) ($line['text'] ?? ''));

                if ($text === '') {
                    continue;
                }

                $key = preg_replace('/\s+/u', ' ', mb_strtolower($text, 'UTF-8')) ?? $text;

                if (isset($seen[$key])) {
                    continue;
                }

                $merged[] = $line;
                $seen[$key] = true;
            }
        }

        return $merged;
    }
}
