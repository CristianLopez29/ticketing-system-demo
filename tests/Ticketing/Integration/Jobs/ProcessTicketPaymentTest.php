<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration\Jobs;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Src\Ticketing\Domain\Events\TicketSold;
use Src\Ticketing\Infrastructure\Jobs\ProcessTicketPayment;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

class ProcessTicketPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    public function test_it_processes_payment_and_creates_ticket(): void
    {
        Event::fake([TicketSold::class]);

        $user = User::factory()->create();

        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat = SeatModel::create([
            'event_id' => $event->id,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'reserved_by_user_id' => $user->id,
        ]);

        $reservationId = 'res-123';
        \Illuminate\Support\Facades\DB::table('reservations')->insert([
            'id' => $reservationId,
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'status' => ReservationStatus::PENDING_PAYMENT->value,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Redis::set("event:{$event->id}:stock", 99);

        // Dispatch Job
        $job = new ProcessTicketPayment($reservationId);
        app()->call([$job, 'handle']);

        // Assert Reservation is PAID
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'status' => ReservationStatus::PAID->value,
        ]);

        // Assert Ticket is created
        $this->assertDatabaseHas('tickets', [
            'event_id' => $event->id,
            'seat_id' => $seat->id,
            'user_id' => $user->id,
        ]);

        Event::assertDispatched(TicketSold::class);
    }
}
