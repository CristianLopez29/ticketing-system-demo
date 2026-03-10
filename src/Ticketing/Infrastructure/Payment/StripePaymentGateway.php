<?php
declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Payment;

use RuntimeException;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Domain\ValueObjects\Money;
use Illuminate\Support\Facades\Log;

class StripePaymentGateway implements PaymentGateway
{
    public function charge(int $userId, Money $amount): string
    {
        // In a real implementation, this would call the Stripe API
        // using Stripe\StripeClient.
        Log::info("Stripe charge initiated", [
            'user_id' => $userId,
            'amount' => $amount->amount(),
            'currency' => $amount->currency()
        ]);

        // Simulate success for the skeleton implementation
        return 'pi_stripe_' . bin2hex(random_bytes(16));
    }
}
