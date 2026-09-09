<?php

use App\Http\Controllers\Api\V1\CivilRegistryScanController;
use App\Http\Controllers\Api\V1\PassportMrzController;
use App\Http\Controllers\Api\V1\PassportScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:'.env('PASSPORT_API_RATE_LIMIT', 60).',1')->group(function (): void {
    Route::get('/meta', fn () => response()->json([
        'name' => 'Libya Document Reader',
        'version' => '0.4.0-dev',
        'scope' => 'Vision-corrected passport and Libyan Civil Registry Authority document scanning with bilingual OCR and structured extraction',
        'privacy' => [
            'stores_document_images' => false,
            'stores_document_data' => false,
            'returns_raw_ocr_text' => false,
        ],
        'supported_documents' => [
            'passport_td3',
            'civil_registry_residence_certificate',
            'civil_registry_family_status_certificate',
        ],
        'vision' => [
            'document_detection' => 'OpenCV contour-based quadrilateral detection with safe fallback',
            'perspective_correction' => true,
            'quality_gate' => ['blur', 'glare', 'overexposure'],
        ],
        'smart_scanner' => [
            'preprocessing' => ['auto_orient', 'grayscale', 'deskew', 'contrast_stretch', 'sharpen'],
            'region_strategy' => 'adaptive TD3 MRZ candidates with ICAO scoring and full-image fallback',
        ],
        'ocr' => [
            'languages' => ['en', 'ar'],
            'document_authenticity_verified' => false,
        ],
    ]));

    Route::post('/passport/mrz/parse', [PassportMrzController::class, 'parse']);
    Route::post('/passport/mrz/validate', [PassportMrzController::class, 'validateMrz']);
    Route::post('/passport/scan', [PassportScanController::class, 'scan']);
    Route::post('/civil-registry/scan', [CivilRegistryScanController::class, 'scan']);
});
