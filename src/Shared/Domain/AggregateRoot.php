<?php

namespace Src\Shared\Domain;

abstract class AggregateRoot
{
    /** @var array<DomainEvent> */
    private array $domainEvents = [];

    final protected function record(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return array<DomainEvent> */
    final public function pullDomainEvents(): array
    {
        $domainEvents = $this->domainEvents;
        $this->domainEvents = [];

        return $domainEvents;
    }
}
