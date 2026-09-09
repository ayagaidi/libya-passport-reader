<?php

namespace App\Services\Passport;

use App\Exceptions\ScannerDependencyException;
use App\Services\Passport\Ocr\MrzTextExtractor;
use App\Services\Passport\Ocr\OcrEngineInterface;
use App\Services\Passport\VisualZone\PassportVisualZoneComparator;
use App\Services\Passport\VisualZone\VisualZoneFieldExtractor;
use App\Services\Passport\VisualZone\VisualZoneOcrEngineInterface;
use Illuminate\Http\UploadedFile;

final class PassportScanService
{
    public function __construct(
        private readonly TemporaryPassportFileManager $temporaryFiles,
        private readonly PassportDocumentPreparer $documentPreparer,
        private readonly OcrEngineInterface $ocr,
        private readonly MrzTextExtractor $mrzExtractor,
        private readonly MrzParserService $mrzParser,
        private readonly VisualZoneOcrEngineInterface $visualZoneOcr,
        private readonly VisualZoneFieldExtractor $visualZoneExtractor,
        private readonly PassportVisualZoneComparator $visualZoneComparator,
    ) {}

    public function scan(UploadedFile $file): array
    {
        $paths = [];
        $result = null;
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();

        try {
            $sourcePath = $this->temporaryFiles->copyFromUpload($file);
            $paths[] = $sourcePath;

            $imagePath = $this->documentPreparer->prepare($sourcePath, $mimeType);

            if ($imagePath !== $sourcePath) {
                $paths[] = $imagePath;
            }

            $ocrResult = $this->ocr->read($imagePath);
            [$line1, $line2] = $this->mrzExtractor->extract($ocrResult->text);
            $passport = $this->mrzParser->parse($line1, $line2);

            $result = [
                'passport' => $passport,
                'scan' => [
                    'mrz_detected' => true,
                    'ocr_engine' => $ocrResult->engine,
                    'ocr_confidence' => $ocrResult->confidence,
                    'source_type' => $mimeType === 'application/pdf' ? 'pdf' : 'image',
                ],
                'visual_zone' => $this->readVisualZone($imagePath, $passport['data']),
                'privacy' => [
                    'stores_passport_images' => false,
                    'stores_passport_data' => false,
                    'returns_raw_ocr_text' => false,
                ],
            ];
        } finally {
            $this->temporaryFiles->delete($paths);
        }

        $result['privacy']['temporary_files_deleted'] = true;

        return $result;
    }

    private function readVisualZone(string $imagePath, array $mrzData): array
    {
        if (! config('passport.visual_zone.enabled', true)) {
            return [
                'status' => 'disabled',
                'fields' => [],
                'comparison' => null,
            ];
        }

        try {
            $ocrResult = $this->visualZoneOcr->read($imagePath);
        } catch (ScannerDependencyException) {
            return [
                'status' => 'unavailable',
                'reason' => 'visual_ocr_unavailable',
                'fields' => [],
                'comparison' => null,
            ];
        }

        $fields = $this->visualZoneExtractor->extract($ocrResult->text);
        $comparison = $this->visualZoneComparator->compare($fields, $mrzData);
        $visualOnlyFields = array_intersect_key(
            $fields,
            array_flip(PassportVisualZoneComparator::VISUAL_ONLY_FIELDS)
        );

        return [
            'status' => $fields === [] ? 'no_fields_detected' : 'processed',
            'ocr_engine' => $ocrResult->engine,
            'ocr_confidence' => $ocrResult->confidence,
            'fields' => $fields,
            'visual_only_fields' => $visualOnlyFields,
            'comparison' => $comparison,
        ];
    }
}
