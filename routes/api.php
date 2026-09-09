<?php

use App\Http\Controllers\Api\V1\CivilRegistryScanController;
use App\Http\Controllers\Api\V1\PassportMrzController;
use App\Http\Controllers\Api\V1\PassportScanController;
use App\Http\Middleware\AuthenticateDocumentApiKey;
use App\Http\Middleware\ThrottleDocumentApiClient;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware([
    AuthenticateDocumentApiKey::class,
    ThrottleDocumentApiClient::class,
])->group(function (): void {
    Route::get('/meta', fn () => response()->json([
        'name' => 'Libya Document Reader',
        'version' => '0.4.2-dev',
        'scope' => 'Production-hardened passport and Libyan Civil Registry Authority document scanning with bilingual OCR, structured extraction, conservative verification signals, API-key authentication, and per-client rate limiting',
        'security' => [
            'api_key_required' => (bool) config('document_api.enabled', false),
            'api_key_header' => (string) config('document_api.header', 'X-API-Key'),
            'rate_limit_scope' => 'per API client',
        ],
        'privacy' => [
            'stores_document_images' => false,
            'stores_document_data' => false,
            'returns_raw_ocr_text' => false,
            'returns_raw_qr_payload' => false,
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
        'civil_registry_verification' => [
            'qr_detection' => true,
            'qr_check_number_structure_validation' => true,
            'seal_detection' => 'blue-ink visual candidates',
            'template_checks' => 'sample-calibrated text anchors and visual positions',
            'issuer_database_checked' => false,
            'document_authenticity_verified' => false,
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
