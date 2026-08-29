<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relaxes the Content-Security-Policy for the one HTML page this API serves.
 *
 * SecurityHeaders applies `default-src 'none'`, which is right for JSON responses but blocks
 * Swagger UI's own script and stylesheet, rendering the page blank. Setting the header here
 * wins because SecurityHeaders only fills in headers the response does not already carry.
 */
class SwaggerUiCsp
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'none'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            // "Try it out" issues same-origin XHR against the documented endpoints.
            "connect-src 'self'",
            "frame-ancestors 'none'",
        ]));

        return $response;
    }
}
