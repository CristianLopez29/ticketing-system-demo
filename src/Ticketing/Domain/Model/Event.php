<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Model;

use Src\Shared\Domain\AggregateRoot;

class Event extends AggregateRoot
{
    private int $id;

    private string $name;

    private int $totalSeats;

    public function __construct(int $id, string $name, int $totalSeats)
    {
        $this->id = $id;
        $this->name = $name;
        $this->totalSeats = $totalSeats;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function totalSeats(): int
    {
        return $this->totalSeats;
    }
}
