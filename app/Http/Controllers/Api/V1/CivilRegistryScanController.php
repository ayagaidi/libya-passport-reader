<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CivilRegistryDocumentNotDetectedException;
use App\Exceptions\ScannerDependencyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanCivilRegistryDocumentRequest;
use App\Services\CivilRegistry\CivilRegistryScanService;
use Illuminate\Http\JsonResponse;

final class CivilRegistryScanController extends Controller
{
    public function scan(ScanCivilRegistryDocumentRequest $request, CivilRegistryScanService $scanner): JsonResponse
    {
        try {
            $result = $scanner->scan($request->file('document'));
        } catch (CivilRegistryDocumentNotDetectedException) {
            return response()->json([
                'message' => 'A supported Libyan Civil Registry Authority document could not be confidently detected.',
                'code' => 'civil_registry_document_not_detected',
            ], 422);
        } catch (ScannerDependencyException) {
            return response()->json([
                'message' => 'The local OCR scanner is temporarily unavailable.',
                'code' => 'scanner_unavailable',
            ], 503);
        }

        return response()->json([
            'data' => $result,
            'meta' => [
                'supported_document_types' => [
                    'residence_certificate',
                    'family_status_certificate',
                ],
                'document_authenticity_verified' => false,
            ],
        ]);
    }
}
