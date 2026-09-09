<?php

use App\Http\Controllers\Api\V1\PassportMrzController;
use App\Http\Controllers\Api\V1\PassportScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:'.env('PASSPORT_API_RATE_LIMIT', 60).',1')->group(function (): void {
    Route::get('/meta', fn () => response()->json([
        'name' => 'Libya Passport Reader',
        'version' => '0.3.2-dev',
        'scope' => 'Adaptive smart passport preprocessing, TD3 MRZ parsing and validation, bilingual visual-zone extraction, and MRZ comparison',
        'privacy' => [
            'stores_passport_images' => false,
            'stores_passport_data' => false,
            'returns_raw_ocr_text' => false,
        ],
        'smart_scanner' => [
            'preprocessing' => ['auto_orient', 'grayscale', 'deskew', 'contrast_stretch', 'sharpen'],
            'region_strategy' => 'adaptive TD3 MRZ candidates with ICAO scoring and full-image fallback',
        ],
        'visual_zone' => [
            'languages' => ['en', 'ar'],
            'document_authenticity_verified' => false,
        ],
    ]));

    Route::post('/passport/mrz/parse', [PassportMrzController::class, 'parse']);
    Route::post('/passport/mrz/validate', [PassportMrzController::class, 'validateMrz']);
    Route::post('/passport/scan', [PassportScanController::class, 'scan']);
});
