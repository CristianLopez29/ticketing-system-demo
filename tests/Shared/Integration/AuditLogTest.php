<?php

namespace Tests\Shared\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Src\Shared\Infrastructure\Persistence\Models\AuditLogModel;

use PHPUnit\Framework\Attributes\Test;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_logs_candidate_registration_to_database(): void
    {
        // Arrange
        $payload = [
            'name' => 'Audit Test Candidate',
            'email' => 'audit@test.com',
            'years_of_experience' => 5,
            'cv' => 'Test CV Content'
        ];

        // Act
        $this->postJson('/api/candidates', $payload)
            ->assertStatus(201);

        // Assert
        // 1. Verify record exists in audit_logs table
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'New Candidate Registered',
            'entity_type' => 'Candidate',
        ]);

        // 2. Verify payload content
        /** @var AuditLogModel|null $log */
        $log = AuditLogModel::latest()->first();

        $this->assertNotNull($log);
        $this->assertEquals('Candidate', $log->entity_type);
        $this->assertEquals('New Candidate Registered', $log->action);

        // Verify JSON payload
        /** @var array<string, mixed> $logPayload */
        $logPayload = $log->payload;
        
        $this->assertEquals('audit@test.com', $logPayload['email'] ?? null);
        $this->assertArrayHasKey('occurred_at', $logPayload);
    }
}
