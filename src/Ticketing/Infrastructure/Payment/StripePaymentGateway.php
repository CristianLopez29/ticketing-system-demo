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
        throw new RuntimeException(
            'Stripe payment gateway is not implemented. '
            . 'Set PAYMENT_GATEWAY_DRIVER=fake for development, '
            . 'or implement the Stripe integration in this class.'
        );
    }
}
