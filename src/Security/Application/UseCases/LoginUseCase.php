<?php

declare(strict_types=1);

namespace Src\Security\Application\UseCases;

use Src\Security\Domain\Exceptions\AuthenticationFailedException;
use Src\Security\Domain\Ports\Authenticator;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditLogger;

class LoginUseCase
{
    public function __construct(
        private readonly Authenticator $authenticator,
        private readonly AuditLogger $auditLogger
    ) {}

    public function execute(string $email, string $password): string
    {
        $token = $this->authenticator->attempt($email, $password);

        if (! $token) {
            $this->auditLogger->log(AuditAction::LoginFailed->value, 'user', $email, null, []);

            throw new AuthenticationFailedException('Invalid credentials.');
        }

        $this->auditLogger->log(AuditAction::LoginSucceeded->value, 'user', $email, null, []);

        return $token;
    }
}
