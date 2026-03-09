<?php

namespace Tests\Ticketing\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationCleanupTest extends TestCase
{
    use RefreshDatabase;

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
            'reserved_by_user_id' => $user->id // Locked
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
            'expires_at' => now()->subMinute(), // Expired 1 min ago
            'created_at' => now()->subMinutes(6),
            'updated_at' => now()->subMinutes(6),
        ]);

        // 2. Run Command
        $this->artisan('ticketing:cleanup-expired-reservations')
             ->expectsOutput('Found 1 expired reservations. Processing...')
             ->expectsOutput("Cleaned up reservation: {$reservationId}")
             ->assertExitCode(0);

        // 3. Verify
        
        // Reservation should be cancelled
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'status' => ReservationStatus::CANCELLED->value
        ]);

        // Seat should be released
        $this->assertDatabaseHas('seats', [
            'id' => $seat->id,
            'reserved_by_user_id' => null
        ]);

        // Stock should be reverted
        $this->assertEquals(100, Redis::get("event:{$event->id}:stock"));
    }
}
