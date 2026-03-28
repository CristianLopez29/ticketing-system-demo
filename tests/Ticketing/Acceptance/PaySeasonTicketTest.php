<?php

declare(strict_types=1);

namespace Tests\Ticketing\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Ticketing\Domain\Enums\ReservationStatus;
use Tests\TestCase;

class PaySeasonTicketTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function createSeasonTicket(int $userId, string $status = 'pending_payment'): string
    {
        $seasonId = DB::table('seasons')->insertGetId([
            'name'       => '2026 Season',
            'start_date' => '2026-01-01',
            'end_date'   => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = (string) \Illuminate\Support\Str::uuid();

        DB::table('season_tickets')->insert([
            'id'             => $id,
            'season_id'      => $seasonId,
            'user_id'        => $userId,
            'row'            => 'A',
            'number'         => 1,
            'price_amount'   => 8000,
            'price_currency' => 'EUR',
            'status'         => $status,
            'expires_at'     => now()->addMinutes(15),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return $id;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────────────────

    public function test_owner_can_pay_their_own_season_ticket(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $ticketId = $this->createSeasonTicket($owner->id);

        $response = $this->postJson("/api/season-tickets/{$ticketId}/pay");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'season_ticket_id' => $ticketId,
            'status'           => ReservationStatus::PAID->value,
        ]);

        $this->assertDatabaseHas('season_tickets', [
            'id'     => $ticketId,
            'status' => ReservationStatus::PAID->value,
        ]);
    }

    public function test_another_user_cannot_pay_a_season_ticket_they_do_not_own(): void
    {
        // Arrange: owner creates ticket, but attacker acts
        $owner   = User::factory()->create();
        $attacker = User::factory()->create();

        $ticketId = $this->createSeasonTicket($owner->id);

        Sanctum::actingAs($attacker);

        // Act
        $response = $this->postJson("/api/season-tickets/{$ticketId}/pay");

        // Assert
        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'You are not authorized to pay for this season ticket.',
        ]);

        // Ticket must remain in pending_payment
        $this->assertDatabaseHas('season_tickets', [
            'id'     => $ticketId,
            'status' => ReservationStatus::PENDING_PAYMENT->value,
        ]);
    }

    public function test_unauthenticated_user_cannot_pay_a_season_ticket(): void
    {
        $owner    = User::factory()->create();
        $ticketId = $this->createSeasonTicket($owner->id);

        // No Sanctum::actingAs — unauthenticated request
        $response = $this->postJson("/api/season-tickets/{$ticketId}/pay");

        $response->assertStatus(401);
    }

    public function test_owner_cannot_pay_an_already_paid_season_ticket(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $ticketId = $this->createSeasonTicket($owner->id, 'paid');

        $response = $this->postJson("/api/season-tickets/{$ticketId}/pay");

        // InvalidStateException → maps to 409 via the existing handler
        $response->assertStatus(409);
        $response->assertJsonFragment([
            'error' => 'Cannot pay for a non-pending season ticket.',
        ]);
    }

    public function test_returns_404_style_error_when_ticket_does_not_exist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/season-tickets/non-existent-uuid/pay');

        // InvalidArgumentException → 400 BAD_REQUEST (see bootstrap/app.php handler)
        $response->assertStatus(400);
        $response->assertJsonFragment([
            'error' => 'Season ticket not found: non-existent-uuid',
        ]);
    }
}
