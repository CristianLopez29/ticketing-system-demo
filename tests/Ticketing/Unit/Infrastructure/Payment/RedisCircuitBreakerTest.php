<?php

declare(strict_types=1);

namespace Tests\Ticketing\Unit\Infrastructure\Payment;

use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Domain\Exceptions\CircuitBreakerOpenException;
use Src\Ticketing\Infrastructure\Payment\RedisCircuitBreaker;
use Tests\TestCase;

/**
 * @group circuit-breaker
 */
class RedisCircuitBreakerTest extends TestCase
{
    private RedisCircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->circuitBreaker = new RedisCircuitBreaker();
        $this->circuitBreaker->reset();
    }

    protected function tearDown(): void
    {
        $this->circuitBreaker->reset();
        parent::tearDown();
    }

    public function test_circuit_is_closed_by_default(): void
    {
        $this->assertFalse($this->circuitBreaker->isOpen());
    }

    public function test_records_failures_incrementally(): void
    {
        $this->circuitBreaker->recordFailure();
        $this->circuitBreaker->recordFailure();

        $this->assertEquals(2, $this->circuitBreaker->failureCount());
        $this->assertFalse($this->circuitBreaker->isOpen());
    }

    public function test_circuit_opens_after_threshold_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $this->assertTrue($this->circuitBreaker->isOpen());
    }

    public function test_guard_throws_when_circuit_is_open(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $this->expectException(CircuitBreakerOpenException::class);

        $this->circuitBreaker->guardOrFail();
    }

    public function test_guard_does_not_throw_when_circuit_is_closed(): void
    {
        $this->circuitBreaker->recordFailure(); // 1 — below threshold

        $this->circuitBreaker->guardOrFail(); // Should not throw

        $this->assertTrue(true); // Reached here — good
    }

    public function test_success_resets_failure_counter(): void
    {
        $this->circuitBreaker->recordFailure();
        $this->circuitBreaker->recordFailure();
        $this->assertEquals(2, $this->circuitBreaker->failureCount());

        $this->circuitBreaker->recordSuccess();

        $this->assertEquals(0, $this->circuitBreaker->failureCount());
        $this->assertFalse($this->circuitBreaker->isOpen());
    }

    public function test_circuit_does_not_open_before_threshold(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $this->assertFalse($this->circuitBreaker->isOpen());
    }

    public function test_reset_clears_all_state(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->circuitBreaker->recordFailure();
        }

        $this->assertTrue($this->circuitBreaker->isOpen());

        $this->circuitBreaker->reset();

        $this->assertFalse($this->circuitBreaker->isOpen());
        $this->assertEquals(0, $this->circuitBreaker->failureCount());
    }
}
