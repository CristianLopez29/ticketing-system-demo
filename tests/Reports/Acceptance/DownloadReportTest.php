<?php

declare(strict_types=1);

namespace Tests\Reports\Acceptance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DownloadReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('reports');
    }

    public function test_it_streams_a_report_that_exists(): void
    {
        $this->actingAsAdmin();
        Storage::disk('reports')->put('q3-sales.csv', "seat,price\nA-1,5000\n");

        $response = $this->get('/api/reports/download?file=q3-sales.csv');

        $response->assertOk();
        $response->assertDownload('q3-sales.csv');
        $this->assertSame("seat,price\nA-1,5000\n", $response->streamedContent());
    }

    public function test_it_answers_404_when_the_report_does_not_exist(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/reports/download?file=missing.csv');

        // ReportNotFoundException → 404 via the handler in bootstrap/app.php
        $response->assertNotFound();
        $response->assertExactJson(['error' => 'Report not found: missing.csv']);
    }

    public function test_it_reduces_a_traversal_attempt_to_a_bare_filename(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/reports/download?file=../../../.env');

        // basename() strips the path, so the lookup never leaves the reports disk
        $response->assertNotFound();
        $response->assertExactJson(['error' => 'Report not found: .env']);
    }

    public function test_it_rejects_a_request_with_no_file_parameter(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/reports/download')->assertStatus(400);
    }

    public function test_it_rejects_an_unauthenticated_request(): void
    {
        $this->getJson('/api/reports/download?file=q3-sales.csv')->assertUnauthorized();
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }
}
