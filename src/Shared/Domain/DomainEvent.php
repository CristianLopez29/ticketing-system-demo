<?php

namespace Src\Shared\Domain;

interface DomainEvent
{
    public function occurredOn(): \DateTimeImmutable;
}
