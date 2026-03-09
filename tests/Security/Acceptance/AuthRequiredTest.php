<?php

namespace Tests\Security\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthRequiredTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_endpoints_require_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'candidate']);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/candidates')->assertStatus(403);
        $this->getJson('/api/evaluators/consolidated')->assertStatus(403);
        $this->postJson('/api/evaluators', [])->assertStatus(403);
        $this->postJson('/api/evaluators/1/assign-candidate', ['candidate_id' => 1])->assertStatus(403);
    }

    #[Test]
    public function admin_can_revoke_all_tokens_for_a_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'candidate']);

        $target->createToken('api');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/users/{$target->id}/tokens/revoke-all")
            ->assertStatus(200)
            ->assertJson(['message' => 'All tokens revoked']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
