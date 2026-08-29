<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration\Persistence;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Ticketing\Domain\ValueObjects\SeatId;
use Src\Ticketing\Infrastructure\Persistence\EloquentSeatRepository;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

class EloquentSeatRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentSeatRepository $repository;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentSeatRepository;
        $this->eventId = EventModel::create(['name' => 'Concert', 'total_seats' => 10])->id;
    }

    public function test_it_locks_nothing_when_the_seat_id_does_not_exist(): void
    {
        $seat = DB::transaction(fn () => $this->repository->findAndLock(new SeatId(404404)));

        $this->assertNull($seat);
    }

    public function test_it_locks_nothing_when_no_seat_sits_at_that_location(): void
    {
        $seat = DB::transaction(
            fn () => $this->repository->findAndLockByLocation($this->eventId, 'Z', 99)
        );

        $this->assertNull($seat);
    }

    public function test_it_maps_a_seat_found_by_location(): void
    {
        $userId = User::factory()->create()->id;
        $stored = SeatModel::create([
            'event_id' => $this->eventId,
            'row' => 'A',
            'number' => 3,
            'price_amount' => 5000,
            'price_currency' => 'EUR',
            'reserved_by_user_id' => $userId,
        ]);

        $seat = DB::transaction(
            fn () => $this->repository->findAndLockByLocation($this->eventId, 'A', 3)
        );

        $this->assertNotNull($seat);
        $this->assertSame($stored->id, $seat->id()->value());
        $this->assertSame('A', $seat->row());
        $this->assertSame(3, $seat->number());
        $this->assertSame(5000, $seat->price()->amount());
        $this->assertSame($userId, $seat->reservedByUserId());
        $this->assertFalse($seat->isAvailable());
    }
}
