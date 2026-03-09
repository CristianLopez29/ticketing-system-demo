<?php

namespace Tests\Security\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TokenManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function refresh_token_issues_a_new_personal_access_token(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $accessToken = $user->createToken('api');
        $plainTextToken = $accessToken->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->postJson('/api/refresh-token')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('token', $response);
        $this->assertNotSame($plainTextToken, $response['token']);

        $this->withHeader('Authorization', 'Bearer ' . $response['token'])
            ->getJson('/api/evaluators/consolidated')
            ->assertStatus(200);
    }

    #[Test]
    public function admin_can_revoke_all_tokens_for_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $target->createToken('api');
        $target->createToken('api');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/users/{$target->id}/tokens/revoke-all")
            ->assertStatus(200)
            ->assertJson([
                'message' => 'All tokens revoked',
            ]);

        $this->assertEquals(0, PersonalAccessToken::where('tokenable_id', $target->id)->count());
    }
}
