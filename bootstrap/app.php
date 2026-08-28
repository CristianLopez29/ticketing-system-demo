<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Src\Reports\Domain\Exceptions\ReportNotFoundException;
use Src\Security\Domain\Exceptions\AuthenticationFailedException;
use Src\Security\Infrastructure\Middleware\EnsureRole;
use Src\Shared\Infrastructure\Middleware\CorrelationId;
use Src\Shared\Infrastructure\Middleware\DocsBasicAuth;
use Src\Shared\Infrastructure\Middleware\RequestLogger;
use Src\Shared\Infrastructure\Middleware\SecurityHeaders;
use Src\Ticketing\Domain\Exceptions\DuplicateRequestException;
use Src\Ticketing\Domain\Exceptions\EventSoldOutException;
use Src\Ticketing\Domain\Exceptions\InvalidStateException;
use Src\Ticketing\Domain\Exceptions\NotSeasonTicketOwnerException;
use Src\Ticketing\Domain\Exceptions\SeatAlreadySoldException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(CorrelationId::class);
        $middleware->append(RequestLogger::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'role' => EnsureRole::class,
            'docs.auth' => DocsBasicAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // No-op while SENTRY_LARAVEL_DSN is unset, so local and CI stay unaffected.
        Integration::handles($exceptions);

        // Every one of these is an expected outcome of a contended sale or a bad request,
        // already answered with a 4xx below. Reporting them would bury real faults: a single
        // sold-out event produces one per losing buyer.
        $exceptions->dontReport([
            AuthenticationFailedException::class,
            DuplicateRequestException::class,
            EventSoldOutException::class,
            InvalidStateException::class,
            NotSeasonTicketOwnerException::class,
            ReportNotFoundException::class,
            SeatAlreadySoldException::class,
        ]);

        $exceptions->render(function (SeatAlreadySoldException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (InvalidStateException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (DuplicateRequestException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (NotSeasonTicketOwnerException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (AuthenticationFailedException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (ReportNotFoundException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (\InvalidArgumentException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        });

        $exceptions->render(function (\RuntimeException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return null;
            }

            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })->create();
