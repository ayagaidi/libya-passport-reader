<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateDocumentApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('document_api.enabled', false)) {
            return $next($request);
        }

        $header = (string) config('document_api.header', 'X-API-Key');
        $providedKey = trim((string) $request->header($header));

        if ($providedKey === '') {
            return $this->unauthorized('missing_api_key');
        }

        $providedHash = hash('sha256', $providedKey);

        foreach ((array) config('document_api.clients', []) as $clientId => $client) {
            $expectedHash = strtolower((string) ($client['key_hash'] ?? ''));

            if ($expectedHash === '' || ! hash_equals($expectedHash, $providedHash)) {
                continue;
            }

            $request->attributes->set('document_api_client_id', (string) $clientId);
            $request->attributes->set(
                'document_api_rate_limit',
                max(1, (int) ($client['rate_limit_per_minute'] ?? config('document_api.default_rate_limit', 60))),
            );

            return $next($request);
        }

        return $this->unauthorized('invalid_api_key');
    }

    private function unauthorized(string $code): JsonResponse
    {
        return response()->json([
            'message' => 'A valid API key is required for this endpoint.',
            'code' => $code,
        ], 401);
    }
}
