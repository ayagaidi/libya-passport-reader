<?php

namespace App\Services\Passport;

use App\Exceptions\ScannerDependencyException;
use Symfony\Component\Process\Process;
use Throwable;

final class PassportDocumentPreparer
{
    public function prepare(string $sourcePath, string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return $sourcePath;
        }

        if ($mimeType !== 'application/pdf') {
            throw new ScannerDependencyException('Unsupported passport document type.');
        }

        $binary = (string) config('passport.pdf.pdftoppm_binary', 'pdftoppm');
        $prefix = $sourcePath.'-page-1';
        $outputPath = $prefix.'.png';
        $process = new Process([
            $binary,
            '-f',
            '1',
            '-singlefile',
            '-r',
            (string) config('passport.pdf.dpi', 300),
            '-png',
            $sourcePath,
            $prefix,
        ]);
        $process->setTimeout((float) config('passport.ocr.timeout', 20));

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new ScannerDependencyException('The PDF rasterizer could not be started.', previous: $exception);
        }

        if (! $process->isSuccessful() || ! is_file($outputPath)) {
            throw new ScannerDependencyException('The PDF could not be prepared for OCR.');
        }

        @chmod($outputPath, 0600);

        return $outputPath;
    }
}
