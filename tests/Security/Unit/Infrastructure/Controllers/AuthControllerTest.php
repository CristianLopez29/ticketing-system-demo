<?php

declare(strict_types=1);

namespace Tests\Security\Unit\Infrastructure\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Src\Security\Application\UseCases\LoginUseCase;
use Src\Security\Infrastructure\Controllers\AuthController;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditLogger;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_it_audits_a_logout(): void
    {
        $user = User::factory()->create();

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')
            ->once()
            ->with(AuditAction::Logout->value, 'user', (string) $user->id, (string) $user->id, []);

        $request = Request::create('/api/logout', 'POST');
        $request->setUserResolver(fn () => $user);

        $controller = new AuthController(Mockery::mock(LoginUseCase::class), $auditLogger);
        $response = $controller->logout($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_audits_a_token_refresh(): void
    {
        $user = User::factory()->create();

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')
            ->once()
            ->with(AuditAction::TokenRefreshed->value, 'user', (string) $user->id, (string) $user->id, []);

        $request = Request::create('/api/refresh-token', 'POST');
        $request->setUserResolver(fn () => $user);

        $controller = new AuthController(Mockery::mock(LoginUseCase::class), $auditLogger);
        $response = $controller->refresh($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_audits_revoking_all_tokens_for_another_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')
            ->once()
            ->with(AuditAction::TokensRevoked->value, 'user', (string) $target->id, (string) $admin->id, []);

        $request = Request::create("/api/users/{$target->id}/tokens/revoke-all", 'POST');
        $request->setUserResolver(fn () => $admin);

        $controller = new AuthController(Mockery::mock(LoginUseCase::class), $auditLogger);
        $response = $controller->revokeAllTokens($request, $target->id);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_does_not_audit_revoking_tokens_of_an_unknown_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldNotReceive('log');

        $request = Request::create('/api/users/404404/tokens/revoke-all', 'POST');
        $request->setUserResolver(fn () => $admin);

        $controller = new AuthController(Mockery::mock(LoginUseCase::class), $auditLogger);
        $response = $controller->revokeAllTokens($request, 404404);

        $this->assertSame(404, $response->getStatusCode());
    }
}
