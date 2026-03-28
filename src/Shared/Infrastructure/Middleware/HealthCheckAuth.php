<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('ticketing.healthcheck_token');
        
        if (!$token || $request->bearerToken() !== $token) {
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
