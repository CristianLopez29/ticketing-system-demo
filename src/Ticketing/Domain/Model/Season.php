<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Model;

use DateTimeImmutable;
use Src\Shared\Domain\AggregateRoot;

class Season extends AggregateRoot
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly DateTimeImmutable $startDate,
        private readonly DateTimeImmutable $endDate,
        private readonly ?int $previousSeasonId = null,
        private readonly ?DateTimeImmutable $renewalStartDate = null,
        private readonly ?DateTimeImmutable $renewalEndDate = null
    ) {
        if ($endDate <= $startDate) {
            throw new \InvalidArgumentException('Season end date must be after start date.');
        }

        if ($renewalStartDate !== null && $renewalEndDate !== null && $renewalEndDate <= $renewalStartDate) {
            throw new \InvalidArgumentException('Season renewal end date must be after renewal start date.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function previousSeasonId(): ?int
    {
        return $this->previousSeasonId;
    }

    public function renewalStartDate(): ?DateTimeImmutable
    {
        return $this->renewalStartDate;
    }

    public function renewalEndDate(): ?DateTimeImmutable
    {
        return $this->renewalEndDate;
    }

    public function isRenewalWindow(DateTimeImmutable $now): bool
    {
        if ($this->renewalStartDate === null || $this->renewalEndDate === null) {
            return false;
        }

        return $now >= $this->renewalStartDate && $now <= $this->renewalEndDate;
    }
}
