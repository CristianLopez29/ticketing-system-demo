<?php

declare(strict_types=1);

namespace Src\Ticketing\Infrastructure\Payment;

use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Domain\Exceptions\CircuitBreakerOpenException;

/**
 * Redis-backed Circuit Breaker for the Payment Gateway.
 *
 * State machine:
 *   CLOSED  → normal operation; failures are counted.
 *   OPEN    → after FAILURE_THRESHOLD consecutive failures; all requests are
 *             rejected immediately with CircuitBreakerOpenException.
 *   (no HALF-OPEN by design — the breaker auto-resets after OPEN_DURATION_SECONDS)
 *
 * Redis keys:
 *   circuit_breaker:payment:failures   – consecutive failure counter (TTL = FAILURE_WINDOW_SECONDS)
 *   circuit_breaker:payment:open_until – Unix timestamp when the breaker may close again
 */
final class RedisCircuitBreaker
{
    private const FAILURE_THRESHOLD        = 5;
    private const FAILURE_WINDOW_SECONDS   = 60;
    private const OPEN_DURATION_SECONDS    = 30;

    private const KEY_FAILURES   = 'circuit_breaker:payment:failures';
    private const KEY_OPEN_UNTIL = 'circuit_breaker:payment:open_until';

    /**
     * @throws CircuitBreakerOpenException when the circuit is open.
     */
    public function guardOrFail(): void
    {
        $openUntil = (int) Redis::get(self::KEY_OPEN_UNTIL);

        if ($openUntil > time()) {
            throw new CircuitBreakerOpenException();
        }
    }

    /**
     * Call on a successful gateway response.
     * Resets the consecutive-failure counter.
     */
    public function recordSuccess(): void
    {
        Redis::del(self::KEY_FAILURES);
    }

    /**
     * Call on a gateway failure.
     * Increments the counter and opens the circuit when the threshold is reached.
     */
    public function recordFailure(): void
    {
        $failures = Redis::incr(self::KEY_FAILURES);

        // Set/renew the TTL so the window slides with each failure
        Redis::expire(self::KEY_FAILURES, self::FAILURE_WINDOW_SECONDS);

        if ($failures >= self::FAILURE_THRESHOLD) {
            Redis::set(self::KEY_OPEN_UNTIL, time() + self::OPEN_DURATION_SECONDS);
        }
    }

    /**
     * Returns true when the circuit is currently open (gateway considered unavailable).
     */
    public function isOpen(): bool
    {
        $openUntil = (int) Redis::get(self::KEY_OPEN_UNTIL);

        return $openUntil > time();
    }

    /**
     * Returns the number of consecutive failures tracked.
     */
    public function failureCount(): int
    {
        return (int) Redis::get(self::KEY_FAILURES);
    }

    /**
     * Resets all circuit breaker state (useful for testing).
     */
    public function reset(): void
    {
        Redis::del(self::KEY_FAILURES);
        Redis::del(self::KEY_OPEN_UNTIL);
    }
}
