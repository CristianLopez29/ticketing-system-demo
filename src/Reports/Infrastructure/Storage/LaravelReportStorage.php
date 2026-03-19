<?php

declare(strict_types=1);

namespace Src\Reports\Infrastructure\Storage;

use Illuminate\Support\Facades\Storage;
use Src\Reports\Domain\Ports\ReportStorage;

class LaravelReportStorage implements ReportStorage
{
    public function exists(string $filename): bool
    {
        return Storage::disk('reports')->exists($filename);
    }

    public function download(string $filename): mixed
    {
        return Storage::disk('reports')->download($filename);
    }
}
