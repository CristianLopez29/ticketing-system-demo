<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Payment;

use RuntimeException;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\ValueObjects\Money;

class FakePaymentGateway implements PaymentGateway
{
    private static bool $shouldFail = false;
    private static bool $shouldFailRefund = false;

    public static function forceFailNextCharge(bool $fail = true): void
    {
        self::$shouldFail = $fail;
    }

    public static function forceFailNextRefund(bool $fail = true): void
    {
        self::$shouldFailRefund = $fail;
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
        if (self::$shouldFailRefund) {
            self::$shouldFailRefund = false;
            throw new \RuntimeException('Refund declined by the bank.');
        }
        // Simulate a refund
    }
}
