<?php

declare(strict_types=1);

namespace Tests\Ticketing\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\PendingCommand;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

class ReservationCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Only flush keys used by this test namespace to avoid destroying shared Redis data in CI
        foreach (Redis::keys('event:*:stock') as $key) {
            Redis::del($key);
        }
    }

    public function test_cleanup_command_releases_expired_reservations(): void
    {
        // 1. Setup
        $user  = User::factory()->create();
        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat  = SeatModel::create([
            'event_id'            => $event->id,
            'row'                 => 'A',
            'number'              => 1,
            'price_amount'        => 5000,
            'price_currency'      => 'USD',
            'reserved_by_user_id' => $user->id, // Locked
        ]);

        // Stock decremented
        Redis::set("event:{$event->id}:stock", 99);

        // Create EXPIRED reservation
        $reservationId = 'res_expired_123';
        DB::table('reservations')->insert([
            'id'             => $reservationId,
            'event_id'       => $event->id,
            'seat_id'        => $seat->id,
            'user_id'        => $user->id,
            'status'         => ReservationStatus::PENDING_PAYMENT->value,
            'price_amount'   => 5000,
            'price_currency' => 'USD',
            'expires_at'     => now()->subMinutes(10), // Expired 10 min ago
            'created_at'     => now()->subMinutes(20),
            'updated_at'     => now()->subMinutes(20),
        ]);

        // 2. Run Command
        $pending = $this->artisan('ticketing:cleanup-expired-reservations');
        if (is_int($pending)) {
            $this->fail('Expected a PendingCommand instance.');
        }

        $this->assertInstanceOf(PendingCommand::class, $pending);

        $pending
            ->expectsOutput("Cleaned up reservation: {$reservationId}")
            ->assertExitCode(0)
            ->run();

        // 3. Verify

        // Reservation should be cancelled
        $this->assertDatabaseHas('reservations', [
            'id'     => $reservationId,
            'status' => ReservationStatus::CANCELLED->value,
        ]);

        // Seat should be released
        $this->assertDatabaseHas('seats', [
            'id'                  => $seat->id,
            'reserved_by_user_id' => null,
        ]);

        // Stock should be reverted
        $this->assertEquals(100, Redis::get("event:{$event->id}:stock"));
    }

    public function test_cleanup_handles_multiple_expired_reservations(): void
    {
        $user  = User::factory()->create();
        $event = EventModel::create(['name' => 'Festival', 'total_seats' => 100]);

        $seat1 = SeatModel::create([
            'event_id'            => $event->id,
            'row'                 => 'A',
            'number'              => 1,
            'price_amount'        => 5000,
            'price_currency'      => 'USD',
            'reserved_by_user_id' => $user->id,
        ]);
        $seat2 = SeatModel::create([
            'event_id'            => $event->id,
            'row'                 => 'A',
            'number'              => 2,
            'price_amount'        => 5000,
            'price_currency'      => 'USD',
            'reserved_by_user_id' => $user->id,
        ]);

        Redis::set("event:{$event->id}:stock", 98);

        $resId1 = 'res_expired_001';
        $resId2 = 'res_expired_002';

        foreach ([$resId1 => $seat1->id, $resId2 => $seat2->id] as $resId => $seatId) {
            DB::table('reservations')->insert([
                'id'             => $resId,
                'event_id'       => $event->id,
                'seat_id'        => $seatId,
                'user_id'        => $user->id,
                'status'         => ReservationStatus::PENDING_PAYMENT->value,
                'price_amount'   => 5000,
                'price_currency' => 'USD',
                'expires_at'     => now()->subMinutes(10),
                'created_at'     => now()->subMinutes(20),
                'updated_at'     => now()->subMinutes(20),
            ]);
        }

        $this->artisan('ticketing:cleanup-expired-reservations')->assertExitCode(0)->run();

        $this->assertDatabaseHas('reservations', ['id' => $resId1, 'status' => ReservationStatus::CANCELLED->value]);
        $this->assertDatabaseHas('reservations', ['id' => $resId2, 'status' => ReservationStatus::CANCELLED->value]);
        $this->assertDatabaseHas('seats', ['id' => $seat1->id, 'reserved_by_user_id' => null]);
        $this->assertDatabaseHas('seats', ['id' => $seat2->id, 'reserved_by_user_id' => null]);
        $this->assertEquals(100, Redis::get("event:{$event->id}:stock"));
    }

    public function test_cleanup_ignores_already_paid_reservations(): void
    {
        $user  = User::factory()->create();
        $event = EventModel::create(['name' => 'Show', 'total_seats' => 50]);
        $seat  = SeatModel::create([
            'event_id'            => $event->id,
            'row'                 => 'B',
            'number'              => 5,
            'price_amount'        => 3000,
            'price_currency'      => 'USD',
            'reserved_by_user_id' => $user->id,
        ]);

        Redis::set("event:{$event->id}:stock", 49);

        $reservationId = 'res_paid_already';
        DB::table('reservations')->insert([
            'id'             => $reservationId,
            'event_id'       => $event->id,
            'seat_id'        => $seat->id,
            'user_id'        => $user->id,
            'status'         => ReservationStatus::PAID->value, // ALREADY PAID
            'price_amount'   => 3000,
            'price_currency' => 'USD',
            'expires_at'     => now()->subMinutes(10),
            'created_at'     => now()->subMinutes(30),
            'updated_at'     => now()->subMinutes(30),
        ]);

        $this->artisan('ticketing:cleanup-expired-reservations')
            ->expectsOutput('No expired reservations found.')
            ->assertExitCode(0)
            ->run();

        // PAID reservation should remain unchanged
        $this->assertDatabaseHas('reservations', [
            'id'     => $reservationId,
            'status' => ReservationStatus::PAID->value,
        ]);
        // Stock should NOT have been reverted
        $this->assertEquals(49, Redis::get("event:{$event->id}:stock"));
    }
}
