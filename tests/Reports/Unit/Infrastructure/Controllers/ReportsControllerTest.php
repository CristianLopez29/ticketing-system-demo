<?php

declare(strict_types=1);

namespace Tests\Reports\Unit\Infrastructure\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Src\Reports\Application\UseCases\DownloadReportUseCase;
use Src\Reports\Infrastructure\Controllers\ReportsController;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditLogger;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ReportsControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_audits_a_successful_report_download(): void
    {
        $user = new User;
        $user->id = 42;

        $useCase = Mockery::mock(DownloadReportUseCase::class);
        $useCase->shouldReceive('execute')
            ->once()
            ->with('q3-sales.csv')
            ->andReturn(new StreamedResponse(fn () => null));

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('log')
            ->once()
            ->with(AuditAction::ReportDownloaded->value, 'report', 'q3-sales.csv', '42', []);

        $request = Request::create('/api/reports/download', 'GET', ['file' => 'q3-sales.csv']);
        $request->setUserResolver(fn () => $user);

        $controller = new ReportsController($useCase, $auditLogger);
        $response = $controller->download($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }
}
