<?php

namespace App\Services\Passport\VisualZone;

use App\DTO\OcrResult;
use App\Exceptions\ScannerDependencyException;
use Symfony\Component\Process\Process;
use Throwable;

final class TesseractVisualZoneOcrEngine implements VisualZoneOcrEngineInterface
{
    public function __construct(private readonly TesseractTsvParser $tsvParser) {}

    public function read(string $imagePath): OcrResult
    {
        $language = (string) config('passport.visual_zone.language', 'eng+ara');
        $fallbackLanguage = (string) config('passport.visual_zone.fallback_language', 'eng');

        $process = $this->run($imagePath, $language);
        $usedLanguage = $language;

        if (! $process->isSuccessful() && $fallbackLanguage !== '' && $fallbackLanguage !== $language) {
            $process = $this->run($imagePath, $fallbackLanguage);
            $usedLanguage = $fallbackLanguage;
        }

        if (! $process->isSuccessful()) {
            throw new ScannerDependencyException('The visual-zone OCR engine failed to process the document.');
        }

        $parsed = $this->tsvParser->parse($process->getOutput());

        return new OcrResult(
            engine: 'tesseract-visual:'.$usedLanguage,
            text: $parsed['text'],
            confidence: $parsed['confidence'],
            metadata: [
                'lines' => $parsed['lines'],
                'format' => 'tsv',
            ],
        );
    }

    private function run(string $imagePath, string $language): Process
    {
        $binary = (string) config('passport.ocr.tesseract_binary', 'tesseract');
        $pageSegmentationMode = (string) config('passport.visual_zone.psm', 6);

        $process = new Process([
            $binary,
            $imagePath,
            'stdout',
            '-l',
            $language,
            '--psm',
            $pageSegmentationMode,
            'tsv',
        ]);
        $process->setTimeout((float) config('passport.visual_zone.timeout', 20));

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new ScannerDependencyException('The visual-zone OCR engine could not be started.', previous: $exception);
        }

        return $process;
    }
}
