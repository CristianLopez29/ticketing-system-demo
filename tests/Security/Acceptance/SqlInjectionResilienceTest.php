<?php

namespace Tests\Security\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SqlInjectionResilienceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function email_contains_filter_is_not_injectable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        $payload = [
            'candidate_email_contains' => "' OR 1=1 --",
        ];

        $this->getJson('/api/evaluators/consolidated?'.http_build_query($payload))
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }
}

