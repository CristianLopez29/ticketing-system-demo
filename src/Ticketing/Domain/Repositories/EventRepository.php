<?php
declare(strict_types=1);

namespace Src\Ticketing\Domain\Repositories;

use Src\Ticketing\Domain\Event;

interface EventRepository
{
    public function find(int $id): ?Event;

    /**
     * @return Event[]
     */
    public function findBySeasonId(int $seasonId): array;
}
