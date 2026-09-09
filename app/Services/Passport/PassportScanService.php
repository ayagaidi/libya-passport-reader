<?php

namespace App\Services\Passport;

use App\Exceptions\LowQualityPassportImageException;
use App\Exceptions\ScannerDependencyException;
use App\Services\Passport\SmartScanner\AdaptiveMrzScanner;
use App\Services\Passport\SmartScanner\SmartPassportImageProcessorInterface;
use App\Services\Passport\Vision\VisionPassportImageProcessorInterface;
use App\Services\Passport\VisualZone\PassportVisualZoneComparator;
use App\Services\Passport\VisualZone\VisualZoneFieldExtractor;
use App\Services\Passport\VisualZone\VisualZoneOcrEngineInterface;
use Illuminate\Http\UploadedFile;

final class PassportScanService
{
    public function __construct(
        private readonly TemporaryPassportFileManager $temporaryFiles,
        private readonly PassportDocumentPreparer $documentPreparer,
        private readonly VisionPassportImageProcessorInterface $visionImageProcessor,
        private readonly SmartPassportImageProcessorInterface $smartImageProcessor,
        private readonly AdaptiveMrzScanner $adaptiveMrzScanner,
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

            $visionImage = $this->visionImageProcessor->prepare($imagePath);
            $paths = array_merge($paths, $visionImage->temporaryPaths);

            if (($visionImage->quality['status'] ?? null) === 'rejected'
                && config('passport.vision.reject_low_quality', true)) {
                throw new LowQualityPassportImageException($visionImage->quality);
            }

            $smartImage = $this->smartImageProcessor->prepare($visionImage->imagePath);
            $paths = array_merge($paths, $smartImage->temporaryPaths);
            $candidatePaths = $smartImage->mrzCandidatePaths !== []
                ? $smartImage->mrzCandidatePaths
                : [$smartImage->mrzImagePath];
            $mrzScan = $this->adaptiveMrzScanner->scan($candidatePaths);
            $ocrResult = $mrzScan['ocr_result'];
            $passport = $this->mrzParser->parse($mrzScan['line1'], $mrzScan['line2']);

            $result = [
                'passport' => $passport,
                'scan' => [
                    'mrz_detected' => true,
                    'ocr_engine' => $ocrResult->engine,
                    'ocr_confidence' => $ocrResult->confidence,
                    'source_type' => $mimeType === 'application/pdf' ? 'pdf' : 'image',
                    'vision' => [
                        'strategy' => $visionImage->strategy,
                        'document_detected' => $visionImage->documentDetected,
                        'perspective_corrected' => $visionImage->perspectiveCorrected,
                        'quality' => $visionImage->quality,
                        'diagnostics' => $visionImage->diagnostics,
                    ],
                    'smart_scanner' => [
                        'strategy' => $smartImage->strategy,
                        'region_detection_applied' => $smartImage->strategy !== 'full_image_fallback',
                        'adaptive_mrz' => [
                            'enabled' => count($candidatePaths) > 1,
                            'candidate_count' => $mrzScan['candidate_count'],
                            'attempts' => $mrzScan['attempts'],
                            'selected_candidate' => $mrzScan['candidate_index'],
                            'detection_score' => $mrzScan['detection_score'],
                        ],
                        'preprocessing' => $smartImage->preprocessing,
                    ],
                ],
                'visual_zone' => $this->readVisualZone($smartImage->visualZoneImagePath, $passport['data']),
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

        $fields = $this->visualZoneExtractor->extract(
            $ocrResult->text,
            $ocrResult->metadata['lines'] ?? [],
        );
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
