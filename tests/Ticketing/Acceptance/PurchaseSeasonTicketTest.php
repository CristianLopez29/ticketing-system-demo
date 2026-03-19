<?php

namespace Tests\Ticketing\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Tests\TestCase;

class PurchaseSeasonTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clean up if needed, though RefreshDatabase handles transaction rollback
    }

    public function test_successfully_purchases_season_ticket_and_reserves_all_seats(): void
    {
        // 1. Arrange
        // Create User
        $user = User::factory()->create();
        $userId = $user->id;
        Sanctum::actingAs($user);

        // Create a Season
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => '2026 Season',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create 2 Events in that Season
        $event1Id = DB::table('events')->insertGetId([
            'name' => 'Match 1',
            'total_seats' => 100,
            'season_id' => $seasonId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event2Id = DB::table('events')->insertGetId([
            'name' => 'Match 2',
            'total_seats' => 100,
            'season_id' => $seasonId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Seats for both events (Row A, Number 1)
        DB::table('seats')->insert([
            [
                'event_id' => $event1Id,
                'row' => 'A',
                'number' => 1,
                'price_amount' => 5000, // 50.00
                'price_currency' => 'EUR',
                'reserved_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_id' => $event2Id,
                'row' => 'A',
                'number' => 1,
                'price_amount' => 6000, // 60.00
                'price_currency' => 'EUR',
                'reserved_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Redis::set("event:{$event1Id}:stock", 100);
        Redis::set("event:{$event2Id}:stock", 100);

        // 2. Act
        $response = $this->postJson('/api/season-tickets/purchase', [
            'season_id' => $seasonId,
            'row' => 'A',
            'number' => 1,
            'idempotency_key' => 'uuid-12345',
        ]);

        // 3. Assert
        $response->assertStatus(201);

        // Check Season Ticket created
        $this->assertDatabaseHas('season_tickets', [
            'season_id' => $seasonId,
            'user_id' => $userId,
            'row' => 'A',
            'number' => 1,
            'status' => ReservationStatus::PENDING_PAYMENT->value,
            // Price should be sum (5000 + 6000) * 0.8 = 11000 * 0.8 = 8800
            'price_amount' => 8800,
        ]);

        // Check Individual Seats are reserved
        $this->assertDatabaseHas('seats', [
            'event_id' => $event1Id,
            'row' => 'A',
            'number' => 1,
            'reserved_by_user_id' => $userId,
        ]);

        $this->assertDatabaseHas('seats', [
            'event_id' => $event2Id,
            'row' => 'A',
            'number' => 1,
            'reserved_by_user_id' => $userId,
        ]);
    }

    public function test_fails_purchase_if_one_seat_is_already_sold(): void
    {
        // 1. Arrange
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => '2026 Season',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event1Id = DB::table('events')->insertGetId([
            'name' => 'Match 1',
            'total_seats' => 100,
            'season_id' => $seasonId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event2Id = DB::table('events')->insertGetId([
            'name' => 'Match 2',
            'total_seats' => 100,
            'season_id' => $seasonId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $otherUserId = $user1->id;
        $userId = $user2->id;
        Sanctum::actingAs($user2);

        DB::table('seats')->insert([
            [
                'event_id' => $event1Id,
                'row' => 'A',
                'number' => 1,
                'price_amount' => 5000,
                'price_currency' => 'EUR',
                'reserved_by_user_id' => null, // Available
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_id' => $event2Id,
                'row' => 'A',
                'number' => 1,
                'price_amount' => 6000,
                'price_currency' => 'EUR',
                'reserved_by_user_id' => $otherUserId, // SOLD!
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Redis::set("event:{$event1Id}:stock", 100);
        Redis::set("event:{$event2Id}:stock", 100);

        // 2. Act
        $response = $this->postJson('/api/season-tickets/purchase', [
            'season_id' => $seasonId,
            'row' => 'A',
            'number' => 1,
            'idempotency_key' => 'uuid-failure',
        ]);

        if ($response->status() !== 409) {
            fwrite(STDERR, 'PurchaseSeasonTicketTest Failure (409): '.$response->content()."\n");
        }

        // 3. Assert
        $response->assertStatus(409); // Conflict
        $response->assertJsonFragment(['error' => "Seat A-1 is already sold for event {$event2Id}"]);

        // Verify NO reservation was made (Atomic Rollback)
        $this->assertDatabaseMissing('season_tickets', [
            'season_id' => $seasonId,
            'user_id' => $userId,
        ]);

        // Verify Seat 1 is still available (rollback worked)
        $this->assertDatabaseHas('seats', [
            'event_id' => $event1Id,
            'row' => 'A',
            'number' => 1,
            'reserved_by_user_id' => null,
        ]);
    }

    public function test_renewal_window_blocks_non_owners(): void
    {
        // 1. Arrange
        // Previous Season
        $prevSeasonId = DB::table('seasons')->insertGetId([
            'name' => '2025 Season',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Previous Owner
        $ownerUser = User::factory()->create();
        $ownerUserId = $ownerUser->id;

        DB::table('season_tickets')->insert([
            'id' => 'old-ticket-id',
            'season_id' => $prevSeasonId,
            'user_id' => $ownerUserId,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 10000,
            'price_currency' => 'EUR',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Current Season with Renewal Window Active
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => '2026 Season',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'previous_season_id' => $prevSeasonId,
            'renewal_start_date' => now()->subDays(1),
            'renewal_end_date' => now()->addDays(10), // Active window
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Event for new season
        $event1Id = DB::table('events')->insertGetId([
            'name' => 'Match 1 2026',
            'total_seats' => 100,
            'season_id' => $seasonId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('seats')->insert([
            'event_id' => $event1Id,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 5000,
            'price_currency' => 'EUR',
            'reserved_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Redis::set("event:{$event1Id}:stock", 100);

        $newUser = User::factory()->create();
        $newUserId = $newUser->id; // NOT the owner
        Sanctum::actingAs($newUser);

        // 2. Act
        $response = $this->postJson('/api/season-tickets/purchase', [
            'season_id' => $seasonId,
            'row' => 'A',
            'number' => 1,
            'idempotency_key' => 'uuid-renewal-fail',
        ]);

        // 3. Assert
        $response->assertStatus(409);
        $response->assertJsonFragment(['error' => 'Renewal Period: This seat is reserved for the previous owner.']);
    }

    public function test_renewal_window_allows_owner(): void
    {
        // 1. Arrange
        // Previous Season
        $prevSeasonId = DB::table('seasons')->insertGetId([
            'name' => '2025 Season',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Previous Owner
        $ownerUser = User::factory()->create();
        $ownerUserId = $ownerUser->id;

        DB::table('season_tickets')->insert([
            'id' => 'old-ticket-id-2',
            'season_id' => $prevSeasonId,
            'user_id' => $ownerUserId,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 10000,
            'price_currency' => 'EUR',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Current Season with Renewal Window Active
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => '2026 Season',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'previous_season_id' => $prevSeasonId,
            'renewal_start_date' => now()->subDays(1),
            'renewal_end_date' => now()->addDays(10), // Active window
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Event for new season
        $event1Id = DB::table('events')->insertGetId([
            'name' => 'Match 1 2026',
            'total_seats' => 100,
            'season_id' => $seasonId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('seats')->insert([
            'event_id' => $event1Id,
            'row' => 'A',
            'number' => 1,
            'price_amount' => 5000,
            'price_currency' => 'EUR',
            'reserved_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Redis::set("event:{$event1Id}:stock", 100);

        Sanctum::actingAs($ownerUser);

        // 2. Act
        $response = $this->postJson('/api/season-tickets/purchase', [
            'season_id' => $seasonId,
            'row' => 'A',
            'number' => 1,
            'idempotency_key' => 'uuid-renewal-success',
        ]);

        if ($response->status() !== 201) {
            fwrite(STDERR, 'PurchaseSeasonTicketTest Failure (Renewal 201): '.$response->content()."\n");
        }

        // 3. Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('season_tickets', [
            'season_id' => $seasonId,
            'user_id' => $ownerUserId,
            'status' => ReservationStatus::PENDING_PAYMENT->value,
        ]);
    }

    public function test_fails_purchase_if_currency_mismatch(): void
    {
        $seasonId = DB::table('seasons')->insertGetId([
            'name' => '2026 Season',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event1Id = DB::table('events')->insertGetId([
            'name' => 'Match 1',
            'total_seats' => 100,
            'season_id' => $seasonId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event2Id = DB::table('events')->insertGetId([
            'name' => 'Match 2',
            'total_seats' => 100,
            'season_id' => $seasonId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        DB::table('seats')->insert([
            [
                'event_id' => $event1Id,
                'row' => 'A',
                'number' => 1,
                'price_amount' => 5000,
                'price_currency' => 'EUR', // EUR
                'reserved_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_id' => $event2Id,
                'row' => 'A',
                'number' => 1,
                'price_amount' => 6000,
                'price_currency' => 'USD', // USD! mismatch
                'reserved_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Redis::set("event:{$event1Id}:stock", 100);
        Redis::set("event:{$event2Id}:stock", 100);

        $response = $this->postJson('/api/season-tickets/purchase', [
            'season_id' => $seasonId,
            'row' => 'A',
            'number' => 1,
            'idempotency_key' => 'uuid-currency-fail',
        ]);

        $response->assertStatus(422); // Validation error handled as 422 Unprocessable Entity for RuntimeException
        $response->assertJsonFragment(['error' => 'Currency mismatch across events in season.']);

        // Stock reverted
        $this->assertEquals(100, Redis::get("event:{$event1Id}:stock"));
        $this->assertEquals(100, Redis::get("event:{$event2Id}:stock"));
    }
}
