<?php

declare(strict_types=1);

namespace Tests\Security\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * The other suites authenticate with Sanctum::actingAs, which never touches the
 * login endpoint. This is the only place the token actually gets issued.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_issues_a_usable_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'password' => Hash::make('correct-horse'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'buyer@example.com',
            'password' => 'correct-horse',
        ]);

        $response->assertStatus(200);

        $token = $response->json('access_token');
        $this->assertIsString($token);
        $this->assertEquals(1, PersonalAccessToken::where('tokenable_id', $user->id)->count());

        // The token has to work against a route behind auth:sanctum.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertStatus(200);
    }

    public function test_it_rejects_a_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'buyer@example.com',
            'password' => Hash::make('correct-horse'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'buyer@example.com',
            'password' => 'wrong',
        ])->assertStatus(401)
            ->assertJsonFragment(['error' => 'Invalid credentials.']);

        $this->assertEquals(0, PersonalAccessToken::count());
    }

    public function test_it_rejects_an_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'correct-horse',
        ])->assertStatus(401);
    }

    public function test_it_requires_a_well_formed_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'not-an-email',
            'password' => 'correct-horse',
        ])->assertStatus(422);
    }
}
