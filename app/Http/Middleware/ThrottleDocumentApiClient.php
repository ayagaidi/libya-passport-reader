<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class ThrottleDocumentApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('document_api.enabled', false)) {
            return $next($request);
        }

        $clientId = (string) $request->attributes->get('document_api_client_id', 'unknown');
        $limit = max(1, (int) $request->attributes->get(
            'document_api_rate_limit',
            config('document_api.default_rate_limit', 60),
        ));
        $bucket = 'document-api-client:'.sha1($clientId);

        if (RateLimiter::tooManyAttempts($bucket, $limit)) {
            $retryAfter = max(1, RateLimiter::availableIn($bucket));

            return $this->tooManyRequests($limit, $retryAfter);
        }

        RateLimiter::hit($bucket, 60);
        $remaining = max(0, RateLimiter::remaining($bucket, $limit));

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }

    private function tooManyRequests(int $limit, int $retryAfter): JsonResponse
    {
        return response()->json([
            'message' => 'The API rate limit for this client has been exceeded.',
            'code' => 'api_rate_limit_exceeded',
            'retry_after_seconds' => $retryAfter,
        ], 429, [
            'Retry-After' => (string) $retryAfter,
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => '0',
        ]);
    }
}
