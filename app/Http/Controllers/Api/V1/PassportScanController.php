<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\LowQualityPassportImageException;
use App\Exceptions\MrzNotDetectedException;
use App\Exceptions\ScannerDependencyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanPassportRequest;
use App\Services\Passport\PassportScanService;
use Illuminate\Http\JsonResponse;

final class PassportScanController extends Controller
{
    public function scan(ScanPassportRequest $request, PassportScanService $scanner): JsonResponse
    {
        try {
            $result = $scanner->scan($request->file('passport'));
        } catch (LowQualityPassportImageException $exception) {
            return response()->json([
                'message' => 'The passport image is too blurry or overexposed for reliable scanning.',
                'code' => 'low_image_quality',
                'quality' => $exception->quality(),
            ], 422);
        } catch (MrzNotDetectedException $exception) {
            return response()->json([
                'message' => 'A TD3 MRZ could not be confidently detected in this document.',
                'code' => 'mrz_not_detected',
            ], 422);
        } catch (ScannerDependencyException $exception) {
            return response()->json([
                'message' => 'The local OCR scanner is temporarily unavailable.',
                'code' => 'scanner_unavailable',
            ], 503);
        }

        return response()->json([
            'data' => $result,
            'meta' => [
                'standard' => 'ICAO Doc 9303 TD3',
                'document_authenticity_verified' => false,
            ],
        ]);
    }
}
