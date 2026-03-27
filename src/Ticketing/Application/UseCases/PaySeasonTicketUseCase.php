<?php

declare(strict_types=1);

namespace Src\Ticketing\Application\UseCases;

use Src\Ticketing\Domain\Model\SeasonTicket;
use Src\Ticketing\Domain\Repositories\SeasonTicketRepository;
use Src\Ticketing\Application\Ports\TransactionManager;
use InvalidArgumentException;

class PaySeasonTicketUseCase
{
    public function __construct(
        private readonly SeasonTicketRepository $seasonTicketRepository,
        private readonly TransactionManager $transactionManager,
    ) {}

    public function execute(string $seasonTicketId): SeasonTicket
    {
        return $this->transactionManager->run(function () use ($seasonTicketId): SeasonTicket {
            $seasonTicket = $this->seasonTicketRepository->find($seasonTicketId);

            if (! $seasonTicket) {
                throw new InvalidArgumentException("Season ticket not found: {$seasonTicketId}");
            }

            $seasonTicket->pay();
            $this->seasonTicketRepository->save($seasonTicket);

            return $seasonTicket;
        });
    }
}
