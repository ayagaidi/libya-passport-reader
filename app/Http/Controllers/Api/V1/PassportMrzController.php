<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParseMrzRequest;
use App\Services\Passport\MrzParserService;
use Illuminate\Http\JsonResponse;

class PassportMrzController extends Controller
{
    public function parse(ParseMrzRequest $request, MrzParserService $parser): JsonResponse
    {
        $result = $parser->parse(
            $request->string('line1')->toString(),
            $request->string('line2')->toString(),
        );

        return response()->json([
            'data' => $result,
            'meta' => [
                'standard' => 'ICAO Doc 9303 TD3',
                'stores_passport_data' => false,
            ],
        ]);
    }

    public function validateMrz(ParseMrzRequest $request, MrzParserService $parser): JsonResponse
    {
        $result = $parser->parse(
            $request->string('line1')->toString(),
            $request->string('line2')->toString(),
        );

        return response()->json([
            'data' => $result['validation'],
        ]);
    }
}
