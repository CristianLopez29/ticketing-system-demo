<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options'            => 'nosniff',
            'X-Frame-Options'                   => 'DENY',
            'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
            // Prevents XSS and content injection
            'Content-Security-Policy'           => "default-src 'none'; frame-ancestors 'none'",
            // Restricts access to browser APIs
            'Permissions-Policy'                => 'geolocation=(), camera=(), microphone=()',
            // Forces HTTPS for one year (includeSubDomains for full coverage)
            'Strict-Transport-Security'         => 'max-age=31536000; includeSubDomains',
            // Protects against plugin-based cross-domain attacks (Flash/PDF)
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
