<?php
declare(strict_types=1);

namespace Src\Ticketing\Domain\Repositories;

use Src\Ticketing\Domain\Model\SeasonTicket;

interface SeasonTicketRepository
{
    public function save(SeasonTicket $seasonTicket): void;

    public function find(string $id): ?SeasonTicket;

    /**
     * Finds a season ticket for a specific user and season.
     * Useful for checking renewals or existing subscriptions.
     * @return SeasonTicket[]
     */
    public function findAllByUserAndSeason(int $userId, int $seasonId): array;

    public function findOneBySeasonAndSeat(int $seasonId, string $row, int $number): ?SeasonTicket;
}
