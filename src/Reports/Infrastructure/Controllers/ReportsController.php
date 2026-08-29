<?php

declare(strict_types=1);

namespace Src\Reports\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Src\Reports\Application\UseCases\DownloadReportUseCase;
use Src\Shared\Domain\Audit\AuditAction;
use Src\Shared\Domain\Audit\AuditLogger;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController
{
    public function __construct(
        private readonly DownloadReportUseCase $downloadReportUseCase,
        private readonly AuditLogger $auditLogger
    ) {}

    public function download(Request $request): StreamedResponse
    {
        $file = $request->query('file');
        $name = is_string($file) ? basename($file) : null;
        if ($name === null) {
            abort(400);
        }

        $response = $this->downloadReportUseCase->execute($name);

        $actor = $request->user();
        $this->auditLogger->log(AuditAction::ReportDownloaded->value, 'report', $name, $actor ? (string) $actor->id : null, []);

        return $response;
    }
}
