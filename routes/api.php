<?php

use App\Http\Controllers\Api\V1\PassportMrzController;
use App\Http\Controllers\Api\V1\PassportScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:'.env('PASSPORT_API_RATE_LIMIT', 60).',1')->group(function (): void {
    Route::get('/meta', fn () => response()->json([
        'name' => 'Libya Passport Reader',
        'version' => '0.3.0-dev',
        'scope' => 'Passport TD3 MRZ parsing, validation, privacy-first OCR scanning, bilingual visual-zone extraction, and MRZ comparison',
        'privacy' => [
            'stores_passport_images' => false,
            'stores_passport_data' => false,
            'returns_raw_ocr_text' => false,
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
