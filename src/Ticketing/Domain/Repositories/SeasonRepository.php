<?php
declare(strict_types=1);

namespace Src\Ticketing\Domain\Repositories;

use Src\Ticketing\Domain\Model\Season;

interface SeasonRepository
{
    public function find(int $id): ?Season;
    
    /**
     * @return Season[]
     */
    public function findAll(): array;
}
