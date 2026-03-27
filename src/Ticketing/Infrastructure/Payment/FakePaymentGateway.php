<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Payment;

use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\ValueObjects\Money;

class FakePaymentGateway implements PaymentGateway
{
    private bool $shouldFail;
    private bool $shouldFailRefund;

    /**
     * @param bool $shouldFail        Force the next charge() to fail (preferred for test setup via constructor)
     * @param bool $shouldFailRefund  Force the next refund() to fail
     */
    public function __construct(
        bool $shouldFail = false,
        bool $shouldFailRefund = false,
    ) {
        $this->shouldFail       = $shouldFail;
        $this->shouldFailRefund = $shouldFailRefund;
    }

    /** @deprecated Prefer injecting failure state via constructor — use new static(shouldFail: true) */
    public function forceFailNextCharge(): self
    {
        $this->shouldFail = true;

        return $this;
    }

    /** @deprecated Prefer injecting failure state via constructor — use new static(shouldFailRefund: true) */
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
