<?php

declare(strict_types=1);

namespace Src\Ticketing\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when the payment gateway circuit breaker is open,
 * meaning the external service is considered unavailable.
 */
final class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(string $message = 'Payment gateway is temporarily unavailable. Please try again later.')
    {
        parent::__construct($message);
    }
}
