<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Ports;

use Src\Ticketing\Domain\ValueObjects\Money;

interface PaymentGateway
{
    /**
     * Charges the user for the given amount.
     * Returns a transaction ID on success.
     * Throws an exception on failure.
     */
    public function charge(int $userId, Money $amount): string;

    /**
     * Refunds a previously charged transaction.
     */
    public function refund(string $transactionId): void;
}
