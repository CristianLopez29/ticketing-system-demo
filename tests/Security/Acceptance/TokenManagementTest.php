<?php

namespace Tests\Security\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TokenManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function refresh_token_issues_a_new_personal_access_token(): void
    {
        Storage::fake('reports');
        Storage::disk('reports')->put('test.csv', 'dummy content');

        $user = User::factory()->create(['role' => 'admin']);
        $accessToken = $user->createToken('api');
        $plainTextToken = (string) $accessToken->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/api/refresh-token');

        if ($response->status() !== 200) {
            throw new \Exception('FAILURE CONTENT: '.$response->content().' | HEADERS: '.json_encode($response->headers->all()));
        }

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertIsArray($json);
        $this->assertArrayHasKey('token', $json);
        $this->assertNotSame($plainTextToken, $json['token']);
        $this->assertIsString($json['token']);

        $this->withHeader('Authorization', 'Bearer '.$json['token'])
            ->getJson('/api/reports/download?file=test.csv')
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
