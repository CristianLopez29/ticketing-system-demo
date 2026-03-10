<?php

namespace Tests\Ticketing\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Src\Ticketing\Domain\Events\TicketSold;
use Src\Ticketing\Domain\Ports\PaymentGateway;
use Src\Ticketing\Infrastructure\Jobs\ProcessTicketPayment;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

class PurchaseTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    public function test_successfully_initiates_purchase_saga(): void
    {
        Event::fake([TicketSold::class]);
        Bus::fake();

        $user = User::factory()->create(['id' => 999]);
        Sanctum::actingAs($user);

        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat = SeatModel::create([
            'event_id' => $event->id,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'reserved_by_user_id' => null,
        ]);

        Redis::set("event:{$event->id}:stock", 100);

        $response = $this->postJson('/api/tickets/purchase', [
            'event_id' => $event->id,
            'seat_id' => $seat->id,
        ], [
            'Idempotency-Key' => 'unique-req-123',
        ]);

        $response->assertStatus(202)
            ->assertJson(['message' => 'Purchase processing started. You will receive a confirmation shortly.']);

        $this->assertDatabaseHas('seats', [
            'id' => $seat->id,
            'reserved_by_user_id' => $user->id, // Locked
        ]);

        $this->assertDatabaseHas('reservations', [
            'seat_id' => $seat->id,
            'user_id' => $user->id,
            'status' => 'pending_payment',
        ]);

        // Ensure ticket is NOT yet created
        $this->assertDatabaseMissing('tickets', [
            'seat_id' => $seat->id,
        ]);

        // Ensure Event is NOT yet dispatched
        Event::assertNotDispatched(TicketSold::class);

        // Ensure Job was dispatched
        Bus::assertDispatched(ProcessTicketPayment::class, function ($job) {
            return true;
        });
    }

    public function test_fails_when_seat_already_sold(): void
    {
        $user = User::factory()->create(['id' => 999]);
        Sanctum::actingAs($user);

        // Mock Payment Gateway (should not be called, but just in case)
        $this->mock(PaymentGateway::class);

        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat = SeatModel::create([
            'event_id' => $event->id,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'reserved_by_user_id' => 123, // Already sold
        ]);

        Redis::set("event:{$event->id}:stock", 100);

        $response = $this->postJson('/api/tickets/purchase', [
            'event_id' => $event->id,
            'seat_id' => $seat->id,
        ], [
            'Idempotency-Key' => 'unique-req-123',
        ]);

        $response->assertStatus(409)
            ->assertJsonFragment(['error' => 'Seat A-1 is already sold.']);

        // Stock should be reverted back to 100 because transaction failed
        $this->assertEquals(100, Redis::get("event:{$event->id}:stock"));
    }

    public function test_idempotency_prevents_duplicate_processing(): void
    {
        Bus::fake();

        $user = User::factory()->create(['id' => 999]);
        Sanctum::actingAs($user);

        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat = SeatModel::create([
            'event_id' => $event->id,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'reserved_by_user_id' => null,
        ]);

        Redis::set("event:{$event->id}:stock", 100);

        // First attempt succeeds
        $this->postJson('/api/tickets/purchase', [
            'event_id' => $event->id,
            'seat_id' => $seat->id,
        ], ['Idempotency-Key' => 'unique-req-123'])->assertStatus(202);

        // Second attempt with exact same key fails
        $response = $this->postJson('/api/tickets/purchase', [
            'event_id' => $event->id,
            'seat_id' => $seat->id,
        ], ['Idempotency-Key' => 'unique-req-123']);

        $response->assertSee('This request has already been processed');
    }
}
