<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Ticketing System API",
 *     description="REST API for the Ticketing System — single-event ticket purchases, season ticket reservations, event management, and health probes.\n\n**Authentication:** All protected endpoints require a Bearer token obtained via `POST /api/login`. Pass it as `Authorization: Bearer {token}`.\n\n**Idempotency:** Purchase endpoints require an `Idempotency-Key` header (UUID recommended) to prevent duplicate charges on retries.",
 *     @OA\Contact(name="Ticketing Team", email="admin@example.com")
 * )
 *
 * @OA\Server(url=L5_SWAGGER_CONST_HOST, description="Demo API Server")
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="apiKey",
 *     in="header",
 *     name="Authorization",
 *     description="Bearer token — format: `Bearer {token}`"
 * )
 *
 * @OA\Tag(name="Tickets",        description="Single-event ticket purchase flow")
 * @OA\Tag(name="Season Tickets", description="Season ticket reservation and payment")
 * @OA\Tag(name="Events",         description="Seat availability and admin statistics")
 * @OA\Tag(name="Health",         description="Kubernetes liveness and readiness probes")
 */
class SwaggerInfo {}
