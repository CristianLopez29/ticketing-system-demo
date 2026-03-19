<?php

declare(strict_types=1);

namespace Src\Security\Domain\Ports;

interface Authenticator
{
    public function attempt(string $email, string $password): ?string;
}
