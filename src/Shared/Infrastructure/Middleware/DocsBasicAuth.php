<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the Swagger UI with HTTP Basic auth.
 *
 * auth:sanctum cannot protect this route: a browser has no way to attach a bearer token, so
 * every visit was redirected to the /login stub and the documentation was unreachable once
 * APP_ENV stopped being "local". Basic auth is the only scheme a browser can satisfy on its own.
 */
class DocsBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('security.docs.protect')) {
            return $next($request);
        }

        $password = config('security.docs.password');

        // Fail closed: a missing password must never be read as "open to everyone".
        if (! is_string($password) || $password === '') {
            return new Response('API documentation is not available.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! $this->credentialsMatch($request, $password)) {
            return new Response('Unauthorized.', Response::HTTP_UNAUTHORIZED, [
                'WWW-Authenticate' => 'Basic realm="API documentation"',
            ]);
        }

        return $next($request);
    }

    private function credentialsMatch(Request $request, string $password): bool
    {
        $username = config('security.docs.username');

        // Both comparisons run unconditionally so the response time does not reveal
        // whether it was the username or the password that was wrong.
        $usernameMatches = hash_equals(is_string($username) ? $username : '', (string) $request->getUser());
        $passwordMatches = hash_equals($password, (string) $request->getPassword());

        return $usernameMatches && $passwordMatches;
    }
}
