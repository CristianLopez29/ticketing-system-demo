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
        // Simulate payment processing delay
        // usleep(100000); // 100ms

        // Simulate random failure (1% chance)
        if (random_int(1, 100) === 1) {
            throw new RuntimeException("Payment failed due to insufficient funds or bank error.");
        }

        return 'txn_' . bin2hex(random_bytes(16));
    }
}
