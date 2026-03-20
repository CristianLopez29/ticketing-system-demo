<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Payment;

use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\ValueObjects\Money;

class FakePaymentGateway implements PaymentGateway
{
    private bool $shouldFail = false;
    private bool $shouldFailRefund = false;

    public function forceFailNextCharge(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    public function forceFailNextRefund(): self
    {
        $this->shouldFailRefund = true;

        return $this;
    }

    public function charge(int $userId, Money $amount): string
    {
        if ($this->shouldFail) {
            $this->shouldFail = false; // reset for next call
            throw new \RuntimeException('Payment declined by the bank.');
        }

        return 'fake_txn_' . uniqid('', true);
    }

    public function refund(string $transactionId): void
    {
        if ($this->shouldFailRefund) {
            $this->shouldFailRefund = false;
            throw new \RuntimeException('Refund declined by the bank.');
        }
        // Simulate a refund
    }
}
