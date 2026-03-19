<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Payment;

use RuntimeException;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\ValueObjects\Money;

class FakePaymentGateway implements PaymentGateway
{
    private static bool $shouldFail = false;

    public static function forceFailNextCharge(bool $fail = true): void
    {
        self::$shouldFail = $fail;
    }

    public function charge(int $userId, Money $amount): string
    {
        if (self::$shouldFail) {
            self::$shouldFail = false; // reset for next call
            throw new \RuntimeException('Payment declined by the bank.');
        }

        return 'fake_txn_' . uniqid('', true);
    }

    public function refund(string $transactionId): void
    {
        // Simulate a refund
    }
}
