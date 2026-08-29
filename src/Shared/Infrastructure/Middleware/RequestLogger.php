<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one structured access-log line per request.
 *
 * Runs inside CorrelationId so every line carries the same correlation_id as the application
 * log entries emitted while handling the request, which is what makes the two files joinable.
 */
class RequestLogger
{
    private const USER_AGENT_MAX_LENGTH = 255;

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        // Never log the request body: it carries passwords on /api/login.
        Log::channel('access')->info('http_request', [
            'method' => $request->getMethod(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, self::USER_AGENT_MAX_LENGTH),
        ]);

        return $response;
    }
}
