<?php

declare(strict_types=1);

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
        // Only flush keys used by this test namespace to avoid destroying shared Redis data in CI
        foreach (Redis::keys('event:*:stock') as $key) {
            Redis::del($key);
        }
        foreach (Redis::keys('purchase:idempotency:*') as $key) {
            Redis::del($key);
        }
    }

    public function test_successfully_initiates_purchase_saga(): void
    {
        Event::fake([TicketSold::class]);
        Bus::fake();

        $user = User::factory()->create();
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
            'Idempotency-Key' => 'a1b2c3d4-e5f6-4789-89ab-cdef01234567',
        ]);

        $response->assertStatus(202)
            ->assertJson(['message' => 'Purchase processing started. You will receive a confirmation shortly.']);

        // reservation_id must be present and be a valid UUID v4
        $reservationId = $response->json('reservation_id');
        $this->assertNotNull($reservationId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) $reservationId,
            'reservation_id should be a valid UUID v4'
        );
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
        Bus::assertDispatched(ProcessTicketPayment::class , function ($job) {
            return true;
        });
    }

    public function test_fails_when_seat_already_sold(): void
    {
        $user = User::factory()->create();
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
            'Idempotency-Key' => 'a1b2c3d4-e5f6-4789-89ab-cdef01234567',
        ]);

        $response->assertStatus(409)
            ->assertJsonFragment(['error' => 'Seat A-1 is already sold.']);

        // Stock should be reverted back to 100 because transaction failed
        $this->assertEquals(100, Redis::get("event:{$event->id}:stock"));
    }

    public function test_idempotency_prevents_duplicate_processing(): void
    {
        Bus::fake();

        $user = User::factory()->create();
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

        // First attempt succeeds and stores the reservation ID
        $first = $this->postJson('/api/tickets/purchase', [
            'event_id' => $event->id,
            'seat_id' => $seat->id,
        ], ['Idempotency-Key' => 'a1b2c3d4-e5f6-4789-89ab-cdef01234567'])->assertStatus(202);

        $firstReservationId = $first->json('reservation_id');

        // Second attempt with the same idempotency key should return 202 and the same reservation ID
        // (idempotent success — operation already completed, return stored result)
        $response = $this->postJson('/api/tickets/purchase', [
            'event_id' => $event->id,
            'seat_id' => $seat->id,
        ], ['Idempotency-Key' => 'a1b2c3d4-e5f6-4789-89ab-cdef01234567']);

        $response->assertStatus(202);
    }

    public function test_rejects_invalid_idempotency_key_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat  = SeatModel::create([
            'event_id'           => $event->id,
            'row'                => 'A',
            'number'             => 1,
            'price_amount'       => 5000,
            'price_currency'     => 'USD',
            'reserved_by_user_id' => null,
        ]);

        $response = $this->postJson('/api/tickets/purchase', [
            'event_id' => $event->id,
            'seat_id'  => $seat->id,
        ], [
            'Idempotency-Key' => 'not-a-valid-uuid',
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['error' => 'Idempotency-Key must be a valid UUID v4.']);
    }

    public function test_accepts_valid_uuid_v4_idempotency_key(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat  = SeatModel::create([
            'event_id'           => $event->id,
            'row'                => 'A',
            'number'             => 2,
            'price_amount'       => 5000,
            'price_currency'     => 'USD',
            'reserved_by_user_id' => null,
        ]);

        Redis::set("event:{$event->id}:stock", 100);

        $response = $this->postJson('/api/tickets/purchase', [
            'event_id' => $event->id,
            'seat_id'  => $seat->id,
        ], [
            'Idempotency-Key' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $response->assertStatus(202);
    }
}
