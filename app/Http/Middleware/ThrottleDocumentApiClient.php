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

        $publicDemoClientId = trim((string) config('document_api.public_demo_client_id', ''));

        if ($publicDemoClientId !== '' && hash_equals($publicDemoClientId, $clientId)) {
            return $this->handlePublicDemo($request, $next, $clientId, $limit);
        }

        $bucket = 'document-api-client:'.sha1($clientId);

        if (RateLimiter::tooManyAttempts($bucket, $limit)) {
            $retryAfter = max(1, RateLimiter::availableIn($bucket));

            return $this->tooManyRequests(
                $limit,
                $retryAfter,
                'api_rate_limit_exceeded',
                'per_client',
            );
        }

        RateLimiter::hit($bucket, 60);
        $remaining = max(0, RateLimiter::remaining($bucket, $limit));

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        $response->headers->set('X-RateLimit-Scope', 'per-client');

        return $response;
    }

    private function handlePublicDemo(Request $request, Closure $next, string $clientId, int $limit): Response
    {
        $ip = (string) ($request->ip() ?: 'unknown');
        $ipBucket = 'document-api-demo-ip:'.sha1($clientId.'|'.$ip);
        $globalBucket = 'document-api-demo-global:'.sha1($clientId);
        $globalLimit = max(1, (int) config('document_api.public_demo_global_rate_limit', 120));

        if (RateLimiter::tooManyAttempts($globalBucket, $globalLimit)) {
            $retryAfter = max(1, RateLimiter::availableIn($globalBucket));

            return $this->tooManyRequests(
                $globalLimit,
                $retryAfter,
                'demo_global_rate_limit_exceeded',
                'public_demo_global',
            );
        }

        if (RateLimiter::tooManyAttempts($ipBucket, $limit)) {
            $retryAfter = max(1, RateLimiter::availableIn($ipBucket));

            return $this->tooManyRequests(
                $limit,
                $retryAfter,
                'api_rate_limit_exceeded',
                'public_demo_per_ip',
            );
        }

        RateLimiter::hit($ipBucket, 60);
        RateLimiter::hit($globalBucket, 60);

        $remaining = max(0, RateLimiter::remaining($ipBucket, $limit));
        $globalRemaining = max(0, RateLimiter::remaining($globalBucket, $globalLimit));

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        $response->headers->set('X-RateLimit-Scope', 'public-demo-per-ip');
        $response->headers->set('X-Demo-Global-RateLimit-Limit', (string) $globalLimit);
        $response->headers->set('X-Demo-Global-RateLimit-Remaining', (string) $globalRemaining);

        return $response;
    }

    private function tooManyRequests(
        int $limit,
        int $retryAfter,
        string $code,
        string $scope,
    ): JsonResponse {
        return response()->json([
            'message' => 'The API rate limit for this client has been exceeded.',
            'code' => $code,
            'rate_limit_scope' => $scope,
            'retry_after_seconds' => $retryAfter,
        ], 429, [
            'Retry-After' => (string) $retryAfter,
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Scope' => str_replace('_', '-', $scope),
        ]);
    }
}
