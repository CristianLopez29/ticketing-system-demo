<?php

namespace Tests\Ticketing\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\PendingCommand;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

class ReservationCleanupTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    public function test_cleanup_command_releases_expired_reservations(): void
    {
        // 1. Setup
        $user = User::factory()->create();
        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat = SeatModel::create([
            'event_id' => $event->id,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'reserved_by_user_id' => $user->id, // Locked
        ]);

        // Stock decremented
        Redis::set("event:{$event->id}:stock", 99);

        // Create EXPIRED reservation
        $reservationId = 'res_expired_123';
        DB::table('reservations')->insert([
            'id' => $reservationId,
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'status' => ReservationStatus::PENDING_PAYMENT->value,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'expires_at' => now()->subMinutes(10), // Expired 10 min ago
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        // 2. Run Command
        $pending = $this->artisan('ticketing:cleanup-expired-reservations');
        if (is_int($pending)) {
            $this->fail('Expected a PendingCommand instance.');
        }

        $this->assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutput('Found 1 expired reservations. Processing...')
            ->expectsOutput("Cleaned up reservation: {$reservationId}")
            ->assertExitCode(0);

        // 3. Verify

        // Reservation should be cancelled
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'status' => ReservationStatus::CANCELLED->value,
        ]);

        // Seat should be released
        $this->assertDatabaseHas('seats', [
            'id' => $seat->id,
            'reserved_by_user_id' => null,
        ]);

        // Stock should be reverted
        $this->assertEquals(100, Redis::get("event:{$event->id}:stock"));
    }
}
