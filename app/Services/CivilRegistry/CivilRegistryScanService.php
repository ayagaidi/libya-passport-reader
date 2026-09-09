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
        private readonly CivilRegistryOcrEngineInterface $ocr,
        private readonly CivilRegistryDocumentClassifier $classifier,
        private readonly CivilRegistryFieldExtractor $extractor,
        private readonly CivilRegistryVerificationService $verification,
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

            // Civil Registry certificates are portrait, full-page layouts. OCR the complete
            // prepared page so the title, QR block and table/header anchors are not lost to
            // passport-oriented perspective crops. Vision output remains diagnostic metadata.
            $ocrResult = $this->ocr->read($imagePath);
            $classification = $this->classifier->detect($ocrResult->text);
            $extracted = $this->extractor->extract(
                $classification['type'],
                $ocrResult->text,
                $ocrResult->metadata['lines'] ?? [],
            );
            $verification = $this->verification->verify(
                $imagePath,
                $classification['type'],
                $ocrResult->text,
                $extracted,
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
                'verification' => $verification,
                'scan' => [
                    'source_type' => $mimeType === 'application/pdf' ? 'pdf' : 'image',
                    'ocr_engine' => $ocrResult->engine,
                    'ocr_confidence' => $ocrResult->confidence,
                    'ocr_scope' => 'full_prepared_document',
                    'layout_candidates' => $ocrResult->metadata['layout_candidates'] ?? [],
                    'vision' => [
                        'strategy' => $visionImage->strategy,
                        'document_detected' => $visionImage->documentDetected,
                        'perspective_corrected' => $visionImage->perspectiveCorrected,
                        'applied_to_ocr' => false,
                        'quality' => $visionImage->quality,
                        'diagnostics' => $visionImage->diagnostics,
                    ],
                ],
                'privacy' => [
                    'stores_document_images' => false,
                    'stores_document_data' => false,
                    'returns_raw_ocr_text' => false,
                    'returns_raw_qr_payload' => false,
                ],
            ];
        } finally {
            $this->temporaryFiles->delete($paths);
        }

        $result['privacy']['temporary_files_deleted'] = true;

        return $result;
    }
}
