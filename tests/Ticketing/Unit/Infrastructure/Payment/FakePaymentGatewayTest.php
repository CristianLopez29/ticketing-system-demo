<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Infrastructure\Payment;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Src\Ticketing\Domain\ValueObjects\Money;
use Src\Ticketing\Infrastructure\Payment\FakePaymentGateway;

class FakePaymentGatewayTest extends TestCase
{
    public function test_it_processes_payment_successfully(): void
    {
        $gateway       = new FakePaymentGateway();
        $transactionId = $gateway->charge(1, new Money(100, 'USD'));

        $this->assertStringStartsWith('fake_txn_', $transactionId);
    }

    public function test_it_can_be_forced_to_fail(): void
    {
        $gateway = (new FakePaymentGateway())->forceFailNextCharge();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment declined by the bank.');

        $gateway->charge(1, new Money(100, 'USD'));
    }

    public function test_it_resets_failure_state_after_failing(): void
    {
        $gateway = (new FakePaymentGateway())->forceFailNextCharge();

        try {
            $gateway->charge(1, new Money(100, 'USD'));
        } catch (RuntimeException $e) {
            // Expected — shouldFail is reset to false inside charge()
        }

        // Second charge on the same instance should succeed
        $transactionId = $gateway->charge(1, new Money(100, 'USD'));
        $this->assertStringStartsWith('fake_txn_', $transactionId);
    }
}
