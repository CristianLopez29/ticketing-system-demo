<?php

declare(strict_types=1);

namespace Tests\Shared\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_serves_a_json_index_at_the_root(): void
    {
        // The root used to render a "welcome" Blade view that does not exist in this repo,
        // so the first page anyone opened was a 500.
        $response = $this->getJson('/');

        $response->assertOk();
        $response->assertJsonStructure(['name', 'status', 'documentation', 'health', 'readiness']);
    }

    public function test_it_reports_ready_when_dependencies_answer(): void
    {
        $response = $this->getJson('/api/readiness');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('checks.database', 'up');
    }

    public function test_it_fails_the_readiness_probe_when_the_database_is_down(): void
    {
        // An uptime monitor watches the status code, so a degraded probe answering 200
        // would never raise an alert.
        DB::shouldReceive('connection')->andThrow(new \RuntimeException('connection refused'));

        $response = $this->getJson('/api/readiness');

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'degraded');
        $response->assertJsonPath('checks.database', 'down');
    }

    public function test_it_throttles_the_unauthenticated_health_probe(): void
    {
        // Laravel 11+ dropped throttle from the default "api" group; without an explicit
        // limit these routes were open to an anonymous flood.
        foreach (range(1, 60) as $ignored) {
            $this->getJson('/api/health')->assertOk();
        }

        $this->getJson('/api/health')->assertStatus(429);
    }

    public function test_it_returns_the_correlation_id_on_every_response(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Correlation-ID');
    }
}
