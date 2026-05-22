<?php

declare(strict_types=1);

namespace Src\Reports\Infrastructure\Storage;

use Illuminate\Support\Facades\Storage;
use Src\Reports\Domain\Exceptions\ReportNotFoundException;
use Src\Reports\Domain\Ports\ReportStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaravelReportStorage implements ReportStorage
{
    public function download(string $filename): StreamedResponse
    {
        if (! Storage::disk('reports')->exists($filename)) {
            throw new ReportNotFoundException("Report not found: {$filename}");
        }

        return Storage::disk('reports')->download($filename);
    }
}
