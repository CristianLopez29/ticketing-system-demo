<?php

declare(strict_types=1);

namespace Src\Reports\Application\UseCases;

use Src\Reports\Domain\Exceptions\ReportNotFoundException;

class DownloadReportUseCase
{
    public function __construct(
        private readonly \Src\Reports\Domain\Ports\ReportStorage $storage
    ) {}

    public function execute(string $filename): mixed
    {
        if (! $this->storage->exists($filename)) {
            throw new ReportNotFoundException("Report not found: {$filename}");
        }

        return $this->storage->download($filename);
    }
}
