<?php

namespace App\Services\Passport\SmartScanner;

use App\DTO\OcrResult;
use App\Exceptions\MrzNotDetectedException;
use App\Services\Passport\Ocr\MrzTextExtractor;
use App\Services\Passport\Ocr\OcrEngineInterface;

final class AdaptiveMrzScanner
{
    public function __construct(
        private readonly OcrEngineInterface $ocr,
        private readonly MrzTextExtractor $extractor,
    ) {}

    public function scan(array $candidatePaths): array
    {
        $candidatePaths = array_values(array_unique(array_filter(
            $candidatePaths,
            static fn (mixed $path): bool => is_string($path) && $path !== ''
        )));

        $best = null;
        $attempts = 0;

        foreach ($candidatePaths as $index => $path) {
            $attempts++;
            $ocrResult = $this->ocr->read($path);

            try {
                $detection = $this->extractor->detect($ocrResult->text);
            } catch (MrzNotDetectedException) {
                continue;
            }

            $qualityScore = $this->qualityScore($detection['score'], $ocrResult);
            $candidate = [
                'ocr_result' => $ocrResult,
                'line1' => $detection['line1'],
                'line2' => $detection['line2'],
                'detection_score' => $detection['score'],
                'quality_score' => $qualityScore,
                'candidate_index' => $index,
            ];

            if ($best === null || $candidate['quality_score'] > $best['quality_score']) {
                $best = $candidate;
            }
        }

        if ($best === null) {
            throw new MrzNotDetectedException('No TD3 MRZ could be confidently detected in any adaptive scan candidate.');
        }

        $best['attempts'] = $attempts;
        $best['candidate_count'] = count($candidatePaths);

        return $best;
    }

    private function qualityScore(int $detectionScore, OcrResult $ocrResult): float
    {
        $confidence = $ocrResult->confidence;
        $confidenceBonus = $confidence === null
            ? 0.0
            : max(0.0, min(100.0, $confidence)) / 100.0;

        return $detectionScore + $confidenceBonus;
    }
}
