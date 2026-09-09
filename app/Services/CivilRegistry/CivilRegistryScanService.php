<?php

namespace App\Services\CivilRegistry;

use App\Services\Passport\PassportDocumentPreparer;
use App\Services\Passport\TemporaryPassportFileManager;
use App\Services\Passport\Vision\VisionPassportImageProcessorInterface;
use Illuminate\Http\UploadedFile;

final class CivilRegistryScanService
{
    public function __construct(
        private readonly TemporaryPassportFileManager $temporaryFiles,
        private readonly PassportDocumentPreparer $documentPreparer,
        private readonly VisionPassportImageProcessorInterface $visionImageProcessor,
        private readonly CivilRegistryOcrEngine $ocr,
        private readonly CivilRegistryDocumentClassifier $classifier,
        private readonly CivilRegistryFieldExtractor $extractor,
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

            $ocrResult = $this->ocr->read($visionImage->imagePath);
            $classification = $this->classifier->detect($ocrResult->text);
            $extracted = $this->extractor->extract(
                $classification['type'],
                $ocrResult->text,
                $ocrResult->metadata['lines'] ?? [],
            );

            $result = [
                'document' => [
                    'type' => $classification['type'],
                    'authority' => 'Civil Registry Authority - Libya',
                    'classification_confidence' => $classification['confidence'],
                    'authority_detected' => $classification['authority_detected'],
                    'fields' => $extracted['fields'],
                    'family_members' => $extracted['family_members'],
                ],
                'scan' => [
                    'source_type' => $mimeType === 'application/pdf' ? 'pdf' : 'image',
                    'ocr_engine' => $ocrResult->engine,
                    'ocr_confidence' => $ocrResult->confidence,
                    'layout_candidates' => $ocrResult->metadata['layout_candidates'] ?? [],
                    'vision' => [
                        'strategy' => $visionImage->strategy,
                        'document_detected' => $visionImage->documentDetected,
                        'perspective_corrected' => $visionImage->perspectiveCorrected,
                        'quality' => $visionImage->quality,
                        'diagnostics' => $visionImage->diagnostics,
                    ],
                ],
                'privacy' => [
                    'stores_document_images' => false,
                    'stores_document_data' => false,
                    'returns_raw_ocr_text' => false,
                ],
            ];
        } finally {
            $this->temporaryFiles->delete($paths);
        }

        $result['privacy']['temporary_files_deleted'] = true;

        return $result;
    }
}
