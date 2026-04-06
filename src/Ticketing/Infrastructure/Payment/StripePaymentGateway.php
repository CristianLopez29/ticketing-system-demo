<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Payment;

use RuntimeException;
use Src\Ticketing\Domain\Exceptions\CircuitBreakerOpenException;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\ValueObjects\Money;
use Throwable;

class StripePaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly RedisCircuitBreaker $circuitBreaker
    ) {}

    public function charge(int $userId, Money $amount): string
    {
        $this->circuitBreaker->guardOrFail();

        try {
            // Connect to Stripe API...
            $transactionId = 'stripe_txn_' . uniqid('', true);

            $this->circuitBreaker->recordSuccess();

            return $transactionId;
        } catch (CircuitBreakerOpenException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->circuitBreaker->recordFailure();
            throw new RuntimeException('Payment gateway charge failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function refund(string $transactionId): void
    {
        $this->circuitBreaker->guardOrFail();

        try {
            // Connect to Stripe API...
            $this->circuitBreaker->recordSuccess();
        } catch (CircuitBreakerOpenException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->circuitBreaker->recordFailure();
            throw new RuntimeException('Payment gateway refund failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
