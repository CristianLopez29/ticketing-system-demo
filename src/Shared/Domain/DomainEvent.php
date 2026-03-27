<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

interface DomainEvent
{
    public function occurredOn(): \DateTimeImmutable;
}
