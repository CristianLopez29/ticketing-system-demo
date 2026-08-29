<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Src\Ticketing\Application\UseCases\PurchaseTicketUseCase;
use Src\Ticketing\Infrastructure\Controllers\PurchaseTicketController;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * `auth:sanctum` already rejects anonymous callers on the registered route, so this
 * pins the controller's own guard: it must never build a DTO with a missing user id
 * if the endpoint is ever remounted without that middleware.
 */
class PurchaseTicketControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_refuses_to_purchase_for_an_unauthenticated_request(): void
    {
        $useCase = Mockery::mock(PurchaseTicketUseCase::class);
        $useCase->shouldNotReceive('execute');

        $request = Request::create('/api/tickets/purchase', 'POST', [
            'event_id' => 1,
            'seat_id' => 1,
        ]);
        $request->headers->set('Idempotency-Key', 'a1b2c3d4-e5f6-4789-89ab-cdef01234567');

        $response = (new PurchaseTicketController($useCase))($request);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('{"error":"Unauthorized"}', $response->getContent());
    }
}
