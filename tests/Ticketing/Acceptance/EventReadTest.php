<?php

namespace Tests\Ticketing\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

class EventReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    public function test_can_fetch_seats_availability(): void
    {
        // 1. Setup
        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 2]);
        $user = User::factory()->create();

        // Seat 1: Sold
        SeatModel::create([
            'event_id' => $event->id,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'reserved_by_user_id' => $user->id,
        ]);

        // Seat 2: Available
        SeatModel::create([
            'event_id' => $event->id,
            'row' => 'A',
            'number' => 2,
            'price_amount' => 5000,
            'price_currency' => 'USD',
            'reserved_by_user_id' => null,
        ]);

        // 2. Act
        $response = $this->getJson("/api/events/{$event->id}/seats");

        // 3. Assert
        $response->assertStatus(200)
            ->assertJsonCount(2);

        $data = $response->json();

        $this->assertEquals('sold', $data[0]['status']);
        $this->assertEquals(1, $data[0]['number']);

        $this->assertEquals('available', $data[1]['status']);
        $this->assertEquals(2, $data[1]['number']);
    }

    public function test_can_fetch_event_stats(): void
    {
        // 1. Setup
        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        Redis::set("event:{$event->id}:stock", 50);

        // Simulate 50 sold seats in DB
        SeatModel::factory()->count(50)->create([
            'event_id' => $event->id,
            'reserved_by_user_id' => 123,
        ]);

        // Simulate 50 available seats
        SeatModel::factory()->count(50)->create([
            'event_id' => $event->id,
            'reserved_by_user_id' => null,
        ]);

        // 2. Act
        $response = $this->getJson("/api/events/{$event->id}/stats");

        // 3. Assert
        $response->assertStatus(200)
            ->assertJson([
                'total_seats' => 100,
                'sold_seats_db' => 50,
                'available_stock_redis' => 50,
                'integrity_check' => 'OK',
            ]);
    }
}
