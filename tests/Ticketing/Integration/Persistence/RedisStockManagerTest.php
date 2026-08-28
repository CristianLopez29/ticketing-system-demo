<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration\Persistence;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\RedisStockManager;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

/**
 * Integration tests for the second barrier of the concurrency contract: the
 * Redis stock counter and its re-hydration path.
 *
 * They run against the real Redis and the real MySQL testing database, because
 * the behaviour under test *is* the interaction between the two — a double
 * would assert nothing.
 */
class RedisStockManagerTest extends TestCase
{
    use RefreshDatabase;

    private RedisStockManager $stockManager;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->forgetRedisKeys('event:*:stock', 'lock:rehydrate:*');

        $this->stockManager = app(RedisStockManager::class);
        $this->eventId = EventModel::create(['name' => 'Test Event', 'total_seats' => 3])->id;
    }

    public function test_it_decrements_an_existing_counter(): void
    {
        $this->stockManager->setStock($this->eventId, 2);

        $this->assertTrue($this->stockManager->attemptToReserve($this->eventId));

        $this->assertSame('1', $this->currentStock());
    }

    public function test_it_refuses_to_reserve_when_the_counter_is_exhausted(): void
    {
        $this->stockManager->setStock($this->eventId, 0);

        $this->assertFalse($this->stockManager->attemptToReserve($this->eventId));

        // A rejected attempt must never push the counter negative
        $this->assertSame('0', $this->currentStock());
    }

    public function test_it_rehydrates_from_the_database_when_the_counter_is_missing(): void
    {
        $this->seedSeats(available: 3, sold: 1);

        // No setStock: this is the Redis-restart / key-eviction scenario
        $this->assertTrue($this->stockManager->attemptToReserve($this->eventId));

        // Re-hydrated to the 3 unreserved seats, then decremented by this reservation
        $this->assertSame('2', $this->currentStock());
        $this->assertNull(Redis::get($this->lockKey()), 'The re-hydration lock must be released');
    }

    public function test_it_does_not_rehydrate_when_the_counter_is_present(): void
    {
        $this->seedSeats(available: 3, sold: 0);
        // Deliberately out of sync with the database: a present counter is
        // authoritative, otherwise every purchase would pay for a COUNT query.
        $this->stockManager->setStock($this->eventId, 1);

        $this->assertTrue($this->stockManager->attemptToReserve($this->eventId));

        $this->assertSame('0', $this->currentStock());
    }

    public function test_it_reports_sold_out_when_rehydration_finds_no_free_seats(): void
    {
        $this->seedSeats(available: 0, sold: 2);

        $this->assertFalse($this->stockManager->attemptToReserve($this->eventId));

        $this->assertSame('0', $this->currentStock());
    }

    public function test_it_takes_the_lock_over_once_a_dead_holder_lets_it_expire(): void
    {
        $this->seedSeats(available: 2, sold: 0);
        // A worker took the lock and died before writing the counter. Its lock
        // expires inside the 3 x 50ms retry window, so this caller takes over.
        Redis::set($this->lockKey(), '1', 'PX', 100);

        $this->assertTrue($this->stockManager->attemptToReserve($this->eventId));

        $this->assertSame('1', $this->currentStock());
        $this->assertNull(Redis::get($this->lockKey()), 'The takeover must release the lock it acquired');
    }

    public function test_it_defers_to_a_live_lock_holder_instead_of_rehydrating_twice(): void
    {
        $this->seedSeats(available: 2, sold: 0);
        // The holder is still alive after the retry window, so it owns the
        // re-hydration. This caller reports no stock rather than racing it.
        Redis::set($this->lockKey(), '1', 'EX', 5);

        $this->assertFalse($this->stockManager->attemptToReserve($this->eventId));

        $this->assertNull($this->currentStock(), 'The deferring caller must not write the counter');

        Redis::del($this->lockKey());
    }

    public function test_revert_reservation_returns_the_unit_to_the_counter(): void
    {
        $this->stockManager->setStock($this->eventId, 1);
        $this->stockManager->attemptToReserve($this->eventId);

        $this->stockManager->revertReservation($this->eventId);

        $this->assertSame('1', $this->currentStock());
    }

    private function seedSeats(int $available, int $sold): void
    {
        $userId = User::factory()->create()->id;
        $number = 1;

        for ($seat = 0; $seat < $available; $seat++) {
            SeatModel::create($this->seatAttributes($number++, null));
        }

        for ($seat = 0; $seat < $sold; $seat++) {
            SeatModel::create($this->seatAttributes($number++, $userId));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function seatAttributes(int $number, ?int $reservedByUserId): array
    {
        return [
            'event_id' => $this->eventId,
            'row' => 'A',
            'number' => $number,
            'price_amount' => 5000,
            'price_currency' => 'EUR',
            'reserved_by_user_id' => $reservedByUserId,
        ];
    }

    private function lockKey(): string
    {
        return "lock:rehydrate:{$this->eventId}";
    }

    private function currentStock(): ?string
    {
        $stock = Redis::get("event:{$this->eventId}:stock");

        return is_string($stock) ? $stock : null;
    }
}
