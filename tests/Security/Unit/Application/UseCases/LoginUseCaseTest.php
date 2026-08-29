<?php

declare(strict_types=1);

namespace Tests\Security\Unit\Application\UseCases;

use Mockery;
use PHPUnit\Framework\TestCase;
use Src\Security\Application\UseCases\LoginUseCase;
use Src\Security\Domain\Exceptions\AuthenticationFailedException;
use Src\Security\Domain\Ports\Authenticator;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditLogger;

class LoginUseCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_it_audits_a_successful_login(): void
    {
        $authenticator = Mockery::mock(Authenticator::class);
        $authenticator->shouldReceive('attempt')
            ->with('buyer@example.com', 'secret')
            ->andReturn('plain-text-token');

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')
            ->once()
            ->with(AuditAction::LoginSucceeded->value, 'user', 'buyer@example.com', null, []);

        $useCase = new LoginUseCase($authenticator, $auditLogger);

        $token = $useCase->execute('buyer@example.com', 'secret');

        $this->assertSame('plain-text-token', $token);
    }

    public function test_it_audits_a_failed_login_and_still_throws(): void
    {
        $authenticator = Mockery::mock(Authenticator::class);
        $authenticator->shouldReceive('attempt')
            ->with('buyer@example.com', 'wrong')
            ->andReturn(null);

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')
            ->once()
            ->with(AuditAction::LoginFailed->value, 'user', 'buyer@example.com', null, []);

        $useCase = new LoginUseCase($authenticator, $auditLogger);

        $this->expectException(AuthenticationFailedException::class);

        $useCase->execute('buyer@example.com', 'wrong');
    }
}
