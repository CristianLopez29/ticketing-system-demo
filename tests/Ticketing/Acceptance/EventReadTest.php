<?php

declare(strict_types=1);

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
        // Only flush keys used by this test namespace to avoid destroying shared Redis data in CI
        foreach (Redis::keys('event:*:stock') as $key) {
            Redis::del($key);
        }
        foreach (Redis::keys('event:*:seats_read_model') as $key) {
            Redis::del($key);
        }
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
        $this->assertIsArray($data);

        $first = $data[0] ?? null;
        $second = $data[1] ?? null;

        $this->assertIsArray($first);
        $this->assertIsArray($second);

        $this->assertEquals('sold', $first['status'] ?? null);
        $this->assertEquals(1, $first['number'] ?? null);

        $this->assertEquals('available', $second['status'] ?? null);
        $this->assertEquals(2, $second['number'] ?? null);
    }

    public function test_can_fetch_event_stats(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        \Laravel\Sanctum\Sanctum::actingAs($admin);

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

    public function test_event_stats_requires_auth(): void
    {
        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $this->getJson("/api/events/{$event->id}/stats")->assertStatus(401);
    }

    public function test_event_stats_requires_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $this->getJson("/api/events/{$event->id}/stats")->assertStatus(403);
    }
}
