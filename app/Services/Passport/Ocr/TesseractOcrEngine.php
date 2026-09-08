<?php

namespace App\Services\Passport\Ocr;

use App\DTO\OcrResult;
use App\Exceptions\MrzNotDetectedException;
use App\Exceptions\ScannerDependencyException;
use Symfony\Component\Process\Process;
use Throwable;

final class TesseractOcrEngine implements OcrEngineInterface
{
    public function read(string $imagePath): OcrResult
    {
        $binary = (string) config('passport.ocr.tesseract_binary', 'tesseract');
        $language = (string) config('passport.ocr.language', 'eng');
        $pageSegmentationMode = (string) config('passport.ocr.psm', 6);

        $process = new Process([
            $binary,
            $imagePath,
            'stdout',
            '-l',
            $language,
            '--psm',
            $pageSegmentationMode,
            '-c',
            'tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
        ]);
        $process->setTimeout((float) config('passport.ocr.timeout', 20));

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new ScannerDependencyException('The OCR engine could not be started.', previous: $exception);
        }

        if (! $process->isSuccessful()) {
            throw new ScannerDependencyException('The OCR engine failed to process the document.');
        }

        $text = trim($process->getOutput());

        if ($text === '') {
            throw new MrzNotDetectedException('No machine-readable text was detected.');
        }

        return new OcrResult(
            engine: 'tesseract',
            text: $text,
        );
    }
}
