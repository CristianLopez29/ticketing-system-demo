<?php

declare(strict_types=1);

namespace Src\Reports\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Src\Reports\Application\UseCases\DownloadReportUseCase;
use Src\Reports\Domain\Exceptions\ReportNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController
{
    public function __construct(
        private readonly DownloadReportUseCase $downloadReportUseCase
    ) {}

    public function download(Request $request): StreamedResponse
    {
        $file = $request->query('file');
        $name = is_string($file) ? basename($file) : null;
        if ($name === null) {
            abort(400);
        }

        try {
            return $this->downloadReportUseCase->execute($name);
        } catch (ReportNotFoundException $e) {
            abort(404);
        }
    }
}
