<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Payment;

use RuntimeException;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\ValueObjects\Money;

class StripePaymentGateway implements PaymentGateway
{
    public function charge(int $userId, Money $amount): string
    {
        // Connect to Stripe API...
        return 'stripe_txn_' . uniqid('', true);
    }

    public function refund(string $transactionId): void
    {
        // Connect to Stripe API...
    }
}
