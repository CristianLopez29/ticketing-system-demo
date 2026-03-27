<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Ticketing\Application\UseCases\ProcessTicketPaymentUseCase;

class ProcessTicketPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Reduced to 3 — fits within the 5-minute reservation TTL (10s+20s+40s = 70s) */
    public int $tries = 3;

    public int $timeout = 30;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Exponential backoff: 10s, 20s, 40s
     */
    public function backoff(): array
    {
        return [10, 20, 40];
    }

    /**
     * Determine the time at which the job should timeout.
     * Aligned with the reservation TTL (5 minutes) — retries past that point
     * will always fail with "Reservation expired or cancelled".
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(5);
    }

    public function __construct(
        public readonly string $reservationId
    ) {}

    public function handle(
        ProcessTicketPaymentUseCase $useCase
    ): void {
        $useCase->execute($this->reservationId);
    }
}