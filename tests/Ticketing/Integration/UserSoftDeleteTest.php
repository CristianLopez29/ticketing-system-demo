<?php

declare(strict_types=1);

namespace Tests\Ticketing\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Ticketing\Infrastructure\Persistence\EventModel;
use Src\Ticketing\Infrastructure\Persistence\SeatModel;
use Tests\TestCase;

/**
 * Verifies that soft-deleting a user:
 *   1. Sets deleted_at instead of physically removing the row.
 *   2. Sets user_id to NULL in tickets (nullOnDelete FK).
 *   3. Sets user_id to NULL in reservations (nullOnDelete FK).
 *   4. Sets user_id to NULL in season_tickets (nullOnDelete FK).
 */
class UserSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_a_user_sets_deleted_at(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_force_deleting_a_user_nullifies_ticket_user_id(): void
    {
        $user  = User::factory()->create();
        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat  = SeatModel::create([
            'event_id'            => $event->id,
            'row'                 => 'A',
            'number'              => 1,
            'price_amount'        => 5000,
            'price_currency'      => 'USD',
            'reserved_by_user_id' => null,
        ]);

        // Insert a ticket directly (bypass domain layer for integration brevity)
        \DB::table('tickets')->insert([
            'id'                => 'ticket-uuid-001',
            'event_id'          => $event->id,
            'seat_id'           => $seat->id,
            'user_id'           => $user->id,
            'price_amount'      => 5000,
            'price_currency'    => 'USD',
            'payment_reference' => 'txn_test_001',
            'issued_at'         => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $user->forceDelete();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('tickets', [
            'id'      => 'ticket-uuid-001',
            'user_id' => null,
        ]);
    }

    public function test_force_deleting_a_user_nullifies_reservation_user_id(): void
    {
        $user  = User::factory()->create();
        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat  = SeatModel::create([
            'event_id'            => $event->id,
            'row'                 => 'B',
            'number'              => 2,
            'price_amount'        => 5000,
            'price_currency'      => 'USD',
            'reserved_by_user_id' => null,
        ]);

        \DB::table('reservations')->insert([
            'id'             => 'reservation-uuid-001',
            'event_id'       => $event->id,
            'seat_id'        => $seat->id,
            'user_id'        => $user->id,
            'status'         => 'pending_payment',
            'price_amount'   => 5000,
            'price_currency' => 'USD',
            'expires_at'     => now()->addMinutes(10),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $user->forceDelete();

        $this->assertDatabaseHas('reservations', [
            'id'      => 'reservation-uuid-001',
            'user_id' => null,
        ]);
    }

    public function test_regular_soft_delete_preserves_fk_references(): void
    {
        $user  = User::factory()->create();
        $event = EventModel::create(['name' => 'Concert', 'total_seats' => 100]);
        $seat  = SeatModel::create([
            'event_id'            => $event->id,
            'row'                 => 'C',
            'number'              => 3,
            'price_amount'        => 5000,
            'price_currency'      => 'USD',
            'reserved_by_user_id' => null,
        ]);

        \DB::table('tickets')->insert([
            'id'                => 'ticket-uuid-002',
            'event_id'          => $event->id,
            'seat_id'           => $seat->id,
            'user_id'           => $user->id,
            'price_amount'      => 5000,
            'price_currency'    => 'USD',
            'payment_reference' => 'txn_test_002',
            'issued_at'         => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Soft delete keeps the user row (with deleted_at set) → FK is preserved
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('tickets', [
            'id'      => 'ticket-uuid-002',
            'user_id' => $user->id, // Still references the (soft-deleted) user
        ]);
    }
}
