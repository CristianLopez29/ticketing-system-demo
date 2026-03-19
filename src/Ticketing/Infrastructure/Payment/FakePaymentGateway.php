<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Payment;

use RuntimeException;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\ValueObjects\Money;

class FakePaymentGateway implements PaymentGateway
{
    public function charge(int $userId, Money $amount): string
    {
        // Simulate random failure (10% chance)
        if (random_int(1, 100) <= 10) {
            throw new \RuntimeException('Payment declined by the bank.');
        }

        return 'fake_txn_' . uniqid('', true);
    }

    public function refund(string $transactionId): void
    {
        // Simulate a refund
    }
}
