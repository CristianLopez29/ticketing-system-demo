<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\UseCases;

use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Src\Ticketing\Application\Ports\TransactionManager;
use Src\Ticketing\Domain\Model\SeasonTicket;
use Src\Ticketing\Domain\Repositories\SeasonTicketRepository;

class PaySeasonTicketUseCase
{
    public function __construct(
        private readonly SeasonTicketRepository $seasonTicketRepository,
        private readonly TransactionManager $transactionManager,
    ) {}

    /**
     * @throws AuthorizationException  If the authenticated user does not own the ticket.
     * @throws InvalidArgumentException If the season ticket is not found.
     */
    public function execute(string $seasonTicketId, int $userId): SeasonTicket
    {
        return $this->transactionManager->run(function () use ($seasonTicketId, $userId): SeasonTicket {
            $seasonTicket = $this->seasonTicketRepository->find($seasonTicketId);

            if (! $seasonTicket) {
                throw new InvalidArgumentException("Season ticket not found: {$seasonTicketId}");
            }

            if ($seasonTicket->userId() !== $userId) {
                throw new AuthorizationException('You are not authorized to pay for this season ticket.');
            }

            $seasonTicket->pay();
            $this->seasonTicketRepository->save($seasonTicket);

            return $seasonTicket;
        });
    }
}
