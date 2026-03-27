<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        // Use incoming correlation ID if present (propagated by upstream service),
        // otherwise generate a fresh one for this request.
        $correlationId = $request->header(self::HEADER) ?? Str::uuid()->toString();

        // Store in log context so all log entries from this request carry it
        Log::withContext(['correlation_id' => $correlationId]);

        $response = $next($request);

        // Always return the correlation ID so callers can trace their requests
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
