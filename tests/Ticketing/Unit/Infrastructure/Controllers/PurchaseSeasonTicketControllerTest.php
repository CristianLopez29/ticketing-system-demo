<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Src\Ticketing\Application\UseCases\PurchaseSeasonTicketUseCase;
use Src\Ticketing\Infrastructure\Controllers\PurchaseSeasonTicketController;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Same guard as PurchaseTicketController: the route is behind `auth:sanctum`, but
 * the controller must not build a DTO with a missing user id on its own.
 */
class PurchaseSeasonTicketControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_refuses_to_purchase_for_an_unauthenticated_request(): void
    {
        $useCase = Mockery::mock(PurchaseSeasonTicketUseCase::class);
        $useCase->shouldNotReceive('execute');

        $request = Request::create('/api/season-tickets/purchase', 'POST', [
            'season_id' => 1,
            'row' => 'A',
            'number' => 1,
            'idempotency_key' => 'a1b2c3d4-e5f6-4789-89ab-cdef01234567',
        ]);

        $response = (new PurchaseSeasonTicketController($useCase))($request);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
