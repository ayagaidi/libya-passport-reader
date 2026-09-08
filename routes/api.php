<?php

use App\Http\Controllers\Api\V1\PassportMrzController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:'.env('PASSPORT_API_RATE_LIMIT', 60).',1')->group(function (): void {
    Route::get('/meta', fn () => response()->json([
        'name' => 'Libya Passport Reader',
        'version' => '0.1.0-dev',
        'scope' => 'Passport TD3 MRZ parsing and validation',
        'privacy' => [
            'stores_passport_images' => false,
            'stores_passport_data' => false,
        ],
    ]));

    Route::post('/passport/mrz/parse', [PassportMrzController::class, 'parse']);
    Route::post('/passport/mrz/validate', [PassportMrzController::class, 'validateMrz']);
});
