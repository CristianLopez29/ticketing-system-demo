<?php

namespace Tests\Security\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SensitiveDataExposureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function evaluator_candidates_list_does_not_expose_cv_content(): void
    {
        $evaluatorId = DB::table('evaluators')->insertGetId([
            'name' => 'Eva One',
            'email' => 'eva@example.com',
            'specialty' => 'Backend',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $candidateId = DB::table('candidates')->insertGetId([
            'name' => 'Cand A',
            'email' => 'cand@example.com',
            'years_of_experience' => 3,
            'cv_content' => 'SECRET CV',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('candidate_assignments')->insert([
            'evaluator_id' => $evaluatorId,
            'candidate_id' => $candidateId,
            'status' => 'pending',
            'assigned_at' => now(),
            'deadline' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson("/api/evaluators/{$evaluatorId}/candidates")
            ->assertStatus(200)
            ->json();

        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertNotEmpty($response['data']);
        $this->assertArrayNotHasKey('cv', $response['data'][0]);
        $this->assertArrayNotHasKey('cv_content', $response['data'][0]);
    }
}
