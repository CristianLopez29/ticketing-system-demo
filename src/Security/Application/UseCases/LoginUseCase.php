<?php

declare(strict_types=1);

namespace Src\Security\Application\UseCases;

use Src\Security\Domain\Exceptions\AuthenticationFailedException;
use Src\Security\Domain\Ports\Authenticator;

class LoginUseCase
{
    public function __construct(
        private readonly Authenticator $authenticator
    ) {}

    public function execute(string $email, string $password): string
    {
        $token = $this->authenticator->attempt($email, $password);

        if (! $token) {
            throw new AuthenticationFailedException('Invalid credentials.');
        }

        return $token;
    }
}
